<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'currency' => 'USD',
            'billing_interval' => 'monthly',
            'base_price' => '29.00',
            'included_units' => 1000,
            'overage_unit_price' => '0.05',
            'active' => true,
        ];
    }
}
