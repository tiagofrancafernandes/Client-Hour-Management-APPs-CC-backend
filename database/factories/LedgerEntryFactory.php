<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'hours' => fake()->randomFloat(2, -20, 50),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'reference_date' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * Configure a credit entry (positive hours).
     */
    public function credit(?float $hours = null): static
    {
        return $this->state(fn () => [
            'hours' => $hours ?? fake()->randomFloat(2, 1, 50),
        ]);
    }

    /**
     * Configure a debit entry (negative hours).
     */
    public function debit(?float $hours = null): static
    {
        $amount = $hours ?? fake()->randomFloat(2, 1, 20);

        return $this->state(fn () => [
            'hours' => -abs($amount),
        ]);
    }
}
