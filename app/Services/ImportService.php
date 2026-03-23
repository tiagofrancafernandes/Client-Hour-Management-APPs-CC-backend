<?php

namespace App\Services;

use App\Models\ImportPlan;
use App\Models\ImportPlanRow;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {
    }

    /**
     * Create a new import plan from uploaded file
     */
    public function createPlan(User $user, Wallet $wallet, UploadedFile $file): ImportPlan
    {
        $this->validateFile($file);

        $filePath = $file->store('imports', 'local');

        $importPlan = ImportPlan::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        $this->parseFile($importPlan);
        $this->validateRows($importPlan);

        return $importPlan->fresh(['rows', 'user', 'wallet']);
    }

    /**
     * Parse file and create import plan rows
     */
    public function parseFile(ImportPlan $plan): Collection
    {
        $filePath = Storage::disk('local')->path($plan->file_path);

        $rows = collect();
        $rowNumber = 1;

        SimpleExcelReader::create($filePath)
            ->getRows()
            ->each(function (array $rowData) use ($plan, &$rows, &$rowNumber) {
                if ($rowNumber === 1) {
                    $rowNumber++;

                    return;
                }

                $hours = $this->calculateHours($rowData);

                $row = ImportPlanRow::create([
                    'import_plan_id' => $plan->id,
                    'row_number' => $rowNumber,
                    'reference_date' => $rowData['reference_date'] ?? now(),
                    'start_time' => $rowData['start_time'] ?? null,
                    'end_time' => $rowData['end_time'] ?? null,
                    'hours' => $hours,
                    'title' => $rowData['title'] ?? '',
                    'description' => $rowData['description'] ?? null,
                    'input_type' => $rowData['input_type'] ?? 'debit',
                    'tags' => $this->parseTags($rowData['tags'] ?? ''),
                    'validation_errors' => [],
                    'is_valid' => true,
                ]);

                $rows->push($row);
                $rowNumber++;
            });

        return $rows;
    }

    /**
     * Validate all rows of an import plan
     */
    public function validateRows(ImportPlan $plan): ImportPlan
    {
        $validCount = 0;
        $invalidCount = 0;
        $totalHours = 0;

        foreach ($plan->rows as $row) {
            $errors = $this->validateRow($row);

            $row->update([
                'validation_errors' => $errors,
                'is_valid' => count($errors) === 0,
            ]);

            if ($row->is_valid) {
                $validCount++;
                $totalHours += $row->hours;
            } else {
                $invalidCount++;
            }
        }

        $plan->update([
            'status' => $invalidCount > 0 ? 'pending' : 'validated',
            'summary' => [
                'total_rows' => $plan->rows->count(),
                'valid_rows' => $validCount,
                'invalid_rows' => $invalidCount,
                'total_hours' => $totalHours,
            ],
        ]);

        return $plan->fresh(['rows']);
    }

    /**
     * Confirm import plan and create ledger entries
     */
    public function confirm(ImportPlan $plan): ImportPlan
    {
        if ($plan->status === 'confirmed') {
            throw new \Exception('Import plan is already confirmed.');
        }

        if ($plan->status === 'cancelled') {
            throw new \Exception('Cannot confirm a cancelled import plan.');
        }

        $invalidRows = $plan->rows->where('is_valid', false);

        if ($invalidRows->count() > 0) {
            throw new \Exception('Cannot confirm import plan with invalid rows. Please fix or remove them first.');
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan->rows as $row) {
                $ledgerEntry = $this->ledgerService->addAdjustment(
                    $plan->wallet,
                    [
                        'reference_date' => $row->reference_date,
                        'hours' => $row->hours,
                        'title' => $row->title,
                        'description' => $row->description,
                        'tags' => $this->resolveTagIds($row->tags),
                    ]
                );

                $row->update([
                    'ledger_entry_id' => $ledgerEntry->id,
                ]);
            }

            $plan->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        });

        return $plan->fresh(['rows.ledgerEntry', 'wallet', 'user']);
    }

    /**
     * Cancel import plan
     */
    public function cancel(ImportPlan $plan): ImportPlan
    {
        if ($plan->status === 'confirmed') {
            throw new \Exception('Cannot cancel a confirmed import plan.');
        }

        $plan->update([
            'status' => 'cancelled',
        ]);

        if ($plan->file_path) {
            Storage::disk('local')->delete($plan->file_path);
        }

        return $plan->fresh();
    }

    /**
     * Update a specific row in the import plan
     */
    public function updateRow(ImportPlanRow $row, array $data): ImportPlanRow
    {
        if ($row->importPlan->status === 'confirmed') {
            throw new \Exception('Cannot update rows of a confirmed import plan.');
        }

        // Calculate hours if start_time and end_time are provided
        $hours = $data['hours'] ?? $row->hours;

        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            $hours = $this->calculateHours([
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'hours' => $hours,
            ]);
        }

        $row->update([
            'reference_date' => $data['reference_date'] ?? $row->reference_date,
            'start_time' => $data['start_time'] ?? $row->start_time,
            'end_time' => $data['end_time'] ?? $row->end_time,
            'hours' => $hours,
            'title' => $data['title'] ?? $row->title,
            'description' => $data['description'] ?? $row->description,
            'input_type' => $data['input_type'] ?? $row->input_type,
            'tags' => $data['tags'] ?? $row->tags,
        ]);

        $errors = $this->validateRow($row);

        $row->update([
            'validation_errors' => $errors,
            'is_valid' => count($errors) === 0,
        ]);

        $this->recalculateSummary($row->importPlan);

        return $row->fresh(['importPlan']);
    }

    /**
     * Add a new row to the import plan
     */
    public function addRow(ImportPlan $plan, array $data): ImportPlanRow
    {
        if ($plan->status === 'confirmed') {
            throw new \Exception('Cannot add rows to a confirmed import plan.');
        }

        $maxRowNumber = $plan->rows()->max('row_number') ?? 0;

        // Calculate hours if start_time and end_time are provided
        $hours = $this->calculateHours($data);

        // Handle tags - can be array or string
        $tags = [];

        if (isset($data['tags'])) {
            if (is_array($data['tags'])) {
                $tags = $data['tags'];
            } elseif (is_string($data['tags'])) {
                $tags = $this->parseTags($data['tags']);
            }
        }

        $row = ImportPlanRow::create([
            'import_plan_id' => $plan->id,
            'row_number' => $maxRowNumber + 1,
            'reference_date' => $data['reference_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'hours' => $hours,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'input_type' => $data['input_type'] ?? 'debit',
            'tags' => $tags,
            'validation_errors' => [],
            'is_valid' => true,
        ]);

        $errors = $this->validateRow($row);

        $row->update([
            'validation_errors' => $errors,
            'is_valid' => count($errors) === 0,
        ]);

        $this->recalculateSummary($plan);

        return $row->fresh(['importPlan']);
    }

    /**
     * Delete a row from the import plan
     */
    public function deleteRow(ImportPlanRow $row): void
    {
        if ($row->importPlan->status === 'confirmed') {
            throw new \Exception('Cannot delete rows from a confirmed import plan.');
        }

        $plan = $row->importPlan;

        $row->delete();

        $this->recalculateSummary($plan);
    }

    /**
     * Generate import template file
     */
    public function generateTemplate(string $format): StreamedResponse
    {
        $headers = [
            'reference_date',
            'start_time',
            'end_time',
            'hours',
            'title',
            'description',
            'input_type',
            'tags',
        ];

        $now = now();
        $today = $now->format('Y-m-d');
        $threeDaysAgo = $now->subDays(3)->format('Y-m-d');

        $exampleData = [
            [
                'reference_date' => $today,
                'start_time' => $today . ' 08:00:00',
                'end_time' => $today . ' 10:30:00',
                'hours' => '',
                'title' => 'Meeting with client',
                'description' => 'Calculated from start/end times (2.5 hours)',
                'input_type' => 'debit',
                'tags' => 'meeting,important',
            ],
            [
                'reference_date' => $today,
                'start_time' => '',
                'end_time' => '',
                'hours' => 5.5,
                'title' => 'Project development',
                'description' => 'Manual hours entry for credit',
                'input_type' => 'credit',
                'tags' => 'development',
            ],
            [
                'reference_date' => $threeDaysAgo,
                'start_time' => '',
                'end_time' => '',
                'hours' => 2,
                'title' => 'Adjustment for previous work',
                'description' => '',
                'input_type' => 'adjustment',
                'tags' => '',
            ],
        ];

        $fileName = 'import_template_' . now()->format('Y_m_d_His') . '.' . $format;

        return response()->streamDownload(function () use ($headers, $exampleData, $format) {
            $tempFile = tempnam(sys_get_temp_dir(), 'import_template') . '.' . $format;

            $writer = SimpleExcelWriter::create($tempFile);

            $writer->addHeader($headers);

            foreach ($exampleData as $row) {
                $writer->addRow($row);
            }

            $writer->close();

            echo file_get_contents($tempFile);

            unlink($tempFile);
        }, $fileName);
    }

    /**
     * Validate file format and size
     */
    private function validateFile(UploadedFile $file): void
    {
        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \Exception('Invalid file format. Only CSV and XLSX files are allowed.');
        }

        $maxSizeInBytes = 10 * 1024 * 1024;

        if ($file->getSize() > $maxSizeInBytes) {
            throw new \Exception('File size exceeds 10MB limit.');
        }
    }

    /**
     * Validate a single row
     */
    private function validateRow(ImportPlanRow $row): array
    {
        $errors = [];

        // Validate reference_date (required)
        if (empty($row->reference_date)) {
            $errors[] = 'Reference date is required.';
        } else {
            try {
                $date = \Carbon\Carbon::parse($row->reference_date);

                if ($date->isFuture()) {
                    $errors[] = 'Reference date cannot be in the future.';
                }
            } catch (\Exception) {
                $errors[] = 'Invalid reference date format.';
            }
        }

        // Validate start_time and end_time (optional but must be valid if present)
        if (!empty($row->start_time)) {
            try {
                $startTime = \Carbon\Carbon::parse($row->start_time);

                if ($startTime->isFuture()) {
                    $errors[] = 'Start time cannot be in the future.';
                }
            } catch (\Exception) {
                $errors[] = 'Invalid start time format.';
            }
        }

        if (!empty($row->end_time)) {
            try {
                $endTime = \Carbon\Carbon::parse($row->end_time);

                if ($endTime->isFuture()) {
                    $errors[] = 'End time cannot be in the future.';
                }
            } catch (\Exception) {
                $errors[] = 'Invalid end time format.';
            }
        }

        // Validate start_time and end_time relationship
        if (!empty($row->start_time) && !empty($row->end_time)) {
            try {
                $startTime = \Carbon\Carbon::parse($row->start_time);
                $endTime = \Carbon\Carbon::parse($row->end_time);

                if ($endTime->lessThanOrEqualTo($startTime)) {
                    $errors[] = 'End time must be after start time.';
                }
            } catch (\Exception) {
                // Already caught in individual validations
            }
        }

        // Validate hours and input_type relationship
        $hasStartAndEnd = !empty($row->start_time) && !empty($row->end_time);
        $hoursRequired = in_array($row->input_type, ['credit', 'adjustment']);

        if ($hoursRequired && empty($row->hours)) {
            $errors[] = "Hours is required for {$row->input_type} input type.";
        } elseif (!$hasStartAndEnd && empty($row->hours)) {
            $errors[] = 'Either hours or both start/end times must be provided.';
        }

        if (!empty($row->hours)) {
            if (!is_numeric($row->hours)) {
                $errors[] = 'Hours must be a number.';
            } elseif ($row->hours == 0) {
                $errors[] = 'Hours cannot be zero.';
            }
        }

        // Validate title (required)
        if (empty($row->title)) {
            $errors[] = 'Title is required.';
        } elseif (strlen($row->title) > 255) {
            $errors[] = 'Title cannot exceed 255 characters.';
        }

        // Validate input_type (debit, credit, adjustment)
        $validInputTypes = ['debit', 'credit', 'adjustment'];

        if (!empty($row->input_type) && !in_array($row->input_type, $validInputTypes)) {
            $errors[] = "Invalid input type. Must be one of: " . implode(', ', $validInputTypes);
        }

        // Validate tags (must be array if present)
        if (!empty($row->tags) && !is_array($row->tags)) {
            $errors[] = 'Tags must be an array.';
        }

        return $errors;
    }

    /**
     * Calculate hours from provided data
     * Returns hours in minutes as decimal
     * If hours is provided, use it; otherwise calculate from start_time and end_time
     */
    private function calculateHours(array $rowData): ?float
    {
        // If hours is explicitly provided, use it
        if (!empty($rowData['hours']) && is_numeric($rowData['hours'])) {
            return (float) $rowData['hours'];
        }

        // If both start_time and end_time are provided, calculate the difference
        if (!empty($rowData['start_time']) && !empty($rowData['end_time'])) {
            try {
                $startTime = \Carbon\Carbon::parse($rowData['start_time']);
                $endTime = \Carbon\Carbon::parse($rowData['end_time']);

                // Calculate difference in minutes and convert to decimal hours
                $diffInMinutes = $startTime->diffInMinutes($endTime);
                $diffInHours = $diffInMinutes / 60;

                return round($diffInHours, 2);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Parse tags string into array
     */
    private function parseTags(string $tagsString): array
    {
        if (empty($tagsString)) {
            return [];
        }

        return array_map('trim', explode(',', $tagsString));
    }

    /**
     * Resolve tag names to IDs
     */
    private function resolveTagIds(?array $tagNames): array
    {
        if (empty($tagNames)) {
            return [];
        }

        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }

    /**
     * Recalculate summary for import plan
     */
    private function recalculateSummary(ImportPlan $plan): void
    {
        $plan->refresh();

        $validCount = 0;
        $invalidCount = 0;
        $totalHours = 0;

        foreach ($plan->rows as $row) {
            if ($row->is_valid) {
                $validCount++;
                $totalHours += $row->hours;
            } else {
                $invalidCount++;
            }
        }

        $plan->update([
            'status' => $invalidCount > 0 ? 'pending' : 'validated',
            'summary' => [
                'total_rows' => $plan->rows->count(),
                'valid_rows' => $validCount,
                'invalid_rows' => $invalidCount,
                'total_hours' => $totalHours,
            ],
        ]);
    }
}
