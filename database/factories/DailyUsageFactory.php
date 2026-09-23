<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DailyUsage> */
class DailyUsageFactory extends Factory
{
    protected $model = DailyUsage::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'subscription_id' => Subscription::factory(),
            'usage_date' => now()->subDay()->toDateString(),
            'quantity' => fake()->numberBetween(1, 1000),
        ];
    }
}
