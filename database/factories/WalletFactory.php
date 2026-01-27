<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->words(2, true) . ' Wallet',
            'description' => fake()->optional()->sentence(),
            'hourly_rate_reference' => fake()->randomFloat(2, 50, 200),
        ];
    }
}
