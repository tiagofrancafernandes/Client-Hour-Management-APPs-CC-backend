<?php

namespace Database\Factories;

use App\Models\ImportPlan;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportPlan>
 */
class ImportPlanFactory extends Factory
{
    protected $model = ImportPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'original_filename' => fake()->word() . '_' . fake()->numberBetween(100, 999) . '.csv',
            'file_path' => 'imports/' . fake()->uuid() . '.csv',
            'status' => 'pending',
            'summary' => null,
            'validation_errors' => null,
            'confirmed_at' => null,
        ];
    }

    /**
     * Set status to validated
     */
    public function validated(): static
    {
        return $this->state(fn () => [
            'status' => 'validated',
            'summary' => [
                'total_rows' => 3,
                'valid_rows' => 3,
                'invalid_rows' => 0,
                'total_hours' => 8.5,
            ],
        ]);
    }

    /**
     * Set status to confirmed
     */
    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => 'confirmed',
            'summary' => [
                'total_rows' => 3,
                'valid_rows' => 3,
                'invalid_rows' => 0,
                'total_hours' => 8.5,
            ],
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Set status to cancelled
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * With invalid rows
     */
    public function withErrors(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'summary' => [
                'total_rows' => 5,
                'valid_rows' => 3,
                'invalid_rows' => 2,
                'total_hours' => 5.5,
            ],
            'validation_errors' => ['Row 2 has invalid date', 'Row 4 is missing hours'],
        ]);
    }
}
