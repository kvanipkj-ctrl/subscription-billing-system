<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    public function test_merchant_relationships(): void
    {
        $merchant = new Merchant();

        $this->assertHasMany($merchant->plans(), Plan::class);
        $this->assertHasMany($merchant->customers(), Customer::class);
    }

    public function test_plan_relationships(): void
    {
        $plan = new Plan();

        $this->assertBelongsTo($plan->merchant(), Merchant::class);
        $this->assertHasMany($plan->subscriptions(), Subscription::class);
    }

    public function test_customer_relationships(): void
    {
        $customer = new Customer();

        $this->assertBelongsTo($customer->merchant(), Merchant::class);
        $this->assertHasMany($customer->subscriptions(), Subscription::class);
        $this->assertHasMany($customer->usageEvents(), UsageEvent::class);
        $this->assertHasMany($customer->dailyUsage(), DailyUsage::class);
        $this->assertHasMany($customer->invoices(), Invoice::class);
    }

    public function test_subscription_relationships(): void
    {
        $subscription = new Subscription();

        $this->assertBelongsTo($subscription->merchant(), Merchant::class);
        $this->assertBelongsTo($subscription->customer(), Customer::class);
        $this->assertBelongsTo($subscription->plan(), Plan::class);
        $this->assertHasMany($subscription->usageEvents(), UsageEvent::class);
    }

    public function test_usage_event_relationships(): void
    {
        $usageEvent = new UsageEvent();

        $this->assertBelongsTo($usageEvent->merchant(), Merchant::class);
        $this->assertBelongsTo($usageEvent->customer(), Customer::class);
        $this->assertBelongsTo($usageEvent->subscription(), Subscription::class);
    }

    public function test_daily_usage_relationships(): void
    {
        $dailyUsage = new DailyUsage();

        $this->assertBelongsTo($dailyUsage->merchant(), Merchant::class);
        $this->assertBelongsTo($dailyUsage->customer(), Customer::class);
        $this->assertBelongsTo($dailyUsage->subscription(), Subscription::class);
    }

    public function test_invoice_relationships(): void
    {
        $invoice = new Invoice();

        $this->assertBelongsTo($invoice->merchant(), Merchant::class);
        $this->assertBelongsTo($invoice->customer(), Customer::class);
        $this->assertBelongsTo($invoice->subscription(), Subscription::class);
        $this->assertHasMany($invoice->invoiceLines(), InvoiceLine::class);
    }

    public function test_invoice_line_relationships(): void
    {
        $invoiceLine = new InvoiceLine();

        $this->assertBelongsTo($invoiceLine->invoice(), Invoice::class);
    }

    private function assertBelongsTo(BelongsTo $relation, string $relatedModel): void
    {
        $this->assertSame($relatedModel, $relation->getRelated()::class);
    }

    private function assertHasMany(HasMany $relation, string $relatedModel): void
    {
        $this->assertSame($relatedModel, $relation->getRelated()::class);
    }
}
