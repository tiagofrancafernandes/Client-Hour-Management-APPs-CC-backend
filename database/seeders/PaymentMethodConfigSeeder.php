<?php

namespace Database\Seeders;

use App\Models\PaymentMethodConfig;
use App\PaymentMethods\PaymentMethodRegistry;
use Illuminate\Database\Seeder;

class PaymentMethodConfigSeeder extends Seeder
{
    public function run(): void
    {
        $methods = PaymentMethodRegistry::all();

        foreach ($methods as $method) {
            PaymentMethodConfig::firstOrCreate(
                ['payment_method_key' => $method->key()],
                [
                    'label' => $method->label(),
                    'is_active' => true,
                    'instructions' => null,
                    'display_order' => 0,
                ]
            );
        }
    }
}
