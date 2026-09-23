<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now()->subDays(10);

        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => null,
        ];
    }
}
