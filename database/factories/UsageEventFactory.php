<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UsageEvent> */
class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'subscription_id' => Subscription::factory(),
            'occurred_at' => now()->subDay(),
            'quantity' => fake()->numberBetween(1, 500),
            'idempotency_key' => fake()->unique()->uuid(),
            'metadata' => null,
        ];
    }
}
