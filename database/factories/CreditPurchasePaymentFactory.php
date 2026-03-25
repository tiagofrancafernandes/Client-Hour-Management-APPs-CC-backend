<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditPurchasePayment>
 */
class CreditPurchasePaymentFactory extends Factory
{
    protected $model = CreditPurchasePayment::class;

    public function definition(): array
    {
        return [
            'credit_purchase_id' => CreditPurchase::factory(),
            'payment_method' => fake()->randomElement(['pix_offline', 'bank_transfer']),
            'payment_status' => PaymentStatus::PENDING,
            'pix_receipt_path' => null,
            'receipt_approved_by' => null,
            'receipt_approved_at' => null,
            'notes' => null,
            'expires_at' => null,
        ];
    }
}
