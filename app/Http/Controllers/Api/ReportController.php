<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {
    }

    public function index(ReportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $groupBy = $request->input('group_by');

        $summary = $this->reportService->getReportSummary($filters);

        $data = [
            'summary' => $summary,
        ];

        if ($groupBy === 'wallet') {
            $data['grouped'] = $this->reportService->getEntriesGroupedByWallet($filters);
        } elseif ($groupBy === 'client') {
            $data['grouped'] = $this->reportService->getEntriesGroupedByClient($filters);
        } else {
            $data['entries'] = $this->reportService->getPaginatedReport(
                $filters,
                $request->input('per_page', 15)
            );
        }

        return response()->json($data);
    }

    public function summary(ReportRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return response()->json($this->reportService->getReportSummary($filters));
    }

    public function byWallet(ReportRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return response()->json([
            'summary' => $this->reportService->getReportSummary($filters),
            'data' => $this->reportService->getEntriesGroupedByWallet($filters),
        ]);
    }

    public function byClient(ReportRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return response()->json([
            'summary' => $this->reportService->getReportSummary($filters),
            'data' => $this->reportService->getEntriesGroupedByClient($filters),
        ]);
    }
}
