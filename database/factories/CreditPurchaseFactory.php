<?php

namespace Database\Factories;

use App\Enums\CreditPurchaseStatus;
use App\Models\CreditPurchase;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditPurchase>
 */
class CreditPurchaseFactory extends Factory
{
    protected $model = CreditPurchase::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'customer_id' => User::factory(),
            'total_hours' => fake()->randomFloat(2, 1, 100),
            'total_price' => fake()->randomFloat(2, 50, 5000),
            'currency_code' => 'BRL',
            'status' => CreditPurchaseStatus::PENDING,
        ];
    }
}
