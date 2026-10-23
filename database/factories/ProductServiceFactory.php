<?php

namespace Database\Factories;

use App\Models\ProductService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductService>
 */
class ProductServiceFactory extends Factory
{
    protected $model = ProductService::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['product', 'service']),
            'unit' => fake()->randomElement(['unit', 'hour', 'month', 'item']),
            'default_price' => fake()->randomFloat(2, 10, 1000),
            'default_quantity' => 1.00,
            'is_active' => true,
        ];
    }
}
