<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password']
        );

        $merchant = Merchant::firstOrCreate(
            ['external_id' => 'demo-merchant-001'],
            ['name' => 'Acme SaaS']
        );
        $plan = Plan::firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Pro'],
            [
                'description' => 'Monthly usage plan',
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'base_price' => '29.00',
                'included_units' => 1000,
                'overage_unit_price' => '0.05',
                'active' => true,
            ]
        );

        $startOfMonth = CarbonImmutable::now()->startOfMonth();
        foreach (range(1, 6) as $index) {
            $customer = Customer::firstOrCreate(
                ['merchant_id' => $merchant->id, 'external_id' => 'demo-customer-00'.$index],
                ['name' => 'Demo Customer '.$index, 'email' => 'demo'.$index.'@example.com']
            );
            $subscription = Subscription::firstOrCreate(
                ['merchant_id' => $merchant->id, 'customer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => $startOfMonth],
                ['status' => 'active', 'ends_at' => null]
            );

            DailyUsage::updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $subscription->id,
                    'usage_date' => $startOfMonth->addDay(),
                ],
                ['quantity' => 200 * $index]
            );

            DailyUsage::updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $subscription->id,
                    'usage_date' => $startOfMonth->subMonth()->addDay(),
                ],
                ['quantity' => 1000 * $index]
            );
        }
    }
}
