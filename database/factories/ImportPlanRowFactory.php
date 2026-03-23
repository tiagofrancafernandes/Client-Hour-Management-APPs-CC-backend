<?php

namespace Database\Factories;

use App\Models\ImportPlan;
use App\Models\ImportPlanRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportPlanRow>
 */
class ImportPlanRowFactory extends Factory
{
    protected $model = ImportPlanRow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_plan_id' => ImportPlan::factory(),
            'row_number' => fake()->numberBetween(1, 100),
            'reference_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'start_time' => null,
            'end_time' => null,
            'hours' => fake()->randomFloat(2, 0.5, 10),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'input_type' => 'debit',
            'tags' => [],
            'validation_errors' => [],
            'is_valid' => true,
            'ledger_entry_id' => null,
        ];
    }

    /**
     * With calculated hours from start/end times
     */
    public function withTimeRange(): static
    {
        $startTime = fake()->dateTimeBetween('-30 days', 'now');
        $endTime = (clone $startTime)->modify('+' . fake()->numberBetween(1, 8) . ' hours');

        return $this->state(function () use ($startTime, $endTime) {
            $diffInMinutes = $startTime->diff($endTime)->i + ($startTime->diff($endTime)->h * 60);
            $hours = round($diffInMinutes / 60, 2);

            return [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hours' => $hours,
            ];
        });
    }

    /**
     * With credit input type
     */
    public function credit(): static
    {
        return $this->state(fn () => [
            'input_type' => 'credit',
        ]);
    }

    /**
     * With adjustment input type
     */
    public function adjustment(): static
    {
        return $this->state(fn () => [
            'input_type' => 'adjustment',
        ]);
    }

    /**
     * With validation errors
     */
    public function invalid(): static
    {
        return $this->state(fn () => [
            'is_valid' => false,
            'validation_errors' => [
                'Hours cannot be zero.',
            ],
        ]);
    }

    /**
     * With tags
     */
    public function withTags(array $tags = []): static
    {
        if (empty($tags)) {
            $tags = [fake()->word(), fake()->word()];
        }

        return $this->state(fn () => [
            'tags' => $tags,
        ]);
    }

    /**
     * Future reference date (for validation testing)
     */
    public function futureDate(): static
    {
        return $this->state(fn () => [
            'reference_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'is_valid' => false,
            'validation_errors' => ['Reference date cannot be in the future.'],
        ]);
    }

    /**
     * Missing title (for validation testing)
     */
    public function missingTitle(): static
    {
        return $this->state(fn () => [
            'title' => '',
            'is_valid' => false,
            'validation_errors' => ['Title is required.'],
        ]);
    }
}
