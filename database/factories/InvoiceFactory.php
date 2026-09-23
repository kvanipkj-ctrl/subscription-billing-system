<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'subscription_id' => Subscription::factory(),
            'billing_period_start' => $start,
            'billing_period_end' => $start->copy()->addMonth(),
            'status' => 'issued',
            'subtotal' => '29.00',
            'total' => '29.00',
            'currency' => 'USD',
            'issued_at' => now(),
        ];
    }
}
