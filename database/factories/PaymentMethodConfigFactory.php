<?php

namespace Database\Factories;

use App\Models\PaymentMethodConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethodConfig>
 */
class PaymentMethodConfigFactory extends Factory
{
    protected $model = PaymentMethodConfig::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $paymentMethods = ['pix_offline', 'bank_transfer', 'credit_card', 'paypal'];
        $index = self::$sequence % count($paymentMethods);
        $paymentMethod = $paymentMethods[$index];
        self::$sequence++;

        return [
            'payment_method_key' => $paymentMethod . '_' . uniqid(),
            'label' => $this->faker->word() . ' Payment',
            'is_active' => $this->faker->boolean(80),
            'instructions' => $this->faker->optional()->sentence(),
            'display_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
