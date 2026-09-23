<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Services\DailyUsageAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_can_be_ingested_and_exact_retry_is_replayed(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $payload = $this->usagePayload($merchant, $customer, $subscription);

        $this->postJson('/api/usage', $payload)
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.quantity', 150)
            ->assertJsonPath('replayed', false);

        $this->postJson('/api/usage', $payload)
            ->assertOk()
            ->assertJsonPath('replayed', true);

        $this->assertDatabaseCount('usage_events', 1);
    }

    public function test_database_unique_constraint_rejects_duplicate_usage_key(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        UsageEvent::factory()->create($this->usageAttributes($merchant, $customer, $subscription, 100, Carbon::parse('2026-09-23'), 'duplicate'));

        $this->expectException(QueryException::class);
        UsageEvent::factory()->create($this->usageAttributes($merchant, $customer, $subscription, 100, Carbon::parse('2026-09-23'), 'duplicate'));
    }

    public function test_usage_key_cannot_be_reused_with_different_payload(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $payload = $this->usagePayload($merchant, $customer, $subscription);
        $this->postJson('/api/usage', $payload)->assertCreated();

        $this->postJson('/api/usage', [...$payload, 'quantity' => 999])
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    public function test_usage_rejects_customer_from_another_merchant(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $otherMerchant = Merchant::factory()->create();

        $this->postJson('/api/usage', [...$this->usagePayload($merchant, $customer, $subscription), 'merchant_id' => $otherMerchant->id])
            ->assertNotFound();
    }

    public function test_daily_usage_aggregation_is_repeatable(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $date = Carbon::parse('2026-09-23');
        UsageEvent::factory()->create($this->usageAttributes($merchant, $customer, $subscription, 40, $date, 'one'));
        UsageEvent::factory()->create($this->usageAttributes($merchant, $customer, $subscription, 60, $date, 'two'));

        $service = app(DailyUsageAggregationService::class);
        $service->aggregateForDate($merchant->id, $customer->id, $subscription->id, $date);
        $service->aggregateForDate($merchant->id, $customer->id, $subscription->id, $date);

        $this->assertDatabaseCount('daily_usage', 1);
        $this->assertDatabaseHas('daily_usage', ['quantity' => 100]);
    }

    public function test_subscription_creation_rejects_cross_tenant_customer_and_plan(): void
    {
        [$merchant] = $this->tenant();
        [$otherMerchant, $otherCustomer, $otherSubscription] = $this->tenant();

        $this->postJson('/api/subscriptions', [
            'merchant_id' => $merchant->id,
            'customer_id' => $otherCustomer->id,
            'plan_id' => $otherSubscription->plan_id,
            'starts_at' => now()->toDateTimeString(),
        ])->assertNotFound();
    }

    public function test_invoice_uses_base_charge_when_usage_is_included(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $period = ['billing_period_start' => '2026-09-01 00:00:00', 'billing_period_end' => '2026-10-01 00:00:00'];
        UsageEvent::factory()->create($this->usageAttributes($merchant, $customer, $subscription, 50, Carbon::parse('2026-09-10'), 'included'));

        $response = $this->postJson('/api/invoices', [...$period, 'merchant_id' => $merchant->id, 'subscription_id' => $subscription->id]);

        $response->assertCreated()->assertJsonPath('data.total', '29.00');
        $this->assertDatabaseCount('invoice_lines', 1);
    }

    public function test_invoice_with_usage_exactly_at_included_units_has_no_overage(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $period = ['billing_period_start' => '2026-09-01 00:00:00', 'billing_period_end' => '2026-10-01 00:00:00'];
        UsageEvent::factory()->create($this->usageAttributes($merchant, $customer, $subscription, 1000, Carbon::parse('2026-09-10'), 'exact-included'));

        $this->postJson('/api/invoices', [...$period, 'merchant_id' => $merchant->id, 'subscription_id' => $subscription->id])
            ->assertCreated()
            ->assertJsonPath('data.total', '29.00');

        $this->assertDatabaseCount('invoice_lines', 1);
    }

    public function test_invoice_adds_overage_charge(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $period = ['billing_period_start' => '2026-09-01 00:00:00', 'billing_period_end' => '2026-10-01 00:00:00'];
        UsageEvent::factory()->create($this->usageAttributes($merchant, $customer, $subscription, 1100, Carbon::parse('2026-09-10'), 'overage'));

        $this->postJson('/api/invoices', [...$period, 'merchant_id' => $merchant->id, 'subscription_id' => $subscription->id])
            ->assertCreated()
            ->assertJsonPath('data.total', '34.00');

        $this->assertDatabaseCount('invoice_lines', 2);
    }

    public function test_duplicate_invoice_generation_returns_existing_invoice(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $payload = [
            'merchant_id' => $merchant->id,
            'subscription_id' => $subscription->id,
            'billing_period_start' => '2026-09-01 00:00:00',
            'billing_period_end' => '2026-10-01 00:00:00',
        ];

        $this->postJson('/api/invoices', $payload)->assertCreated();
        $this->postJson('/api/invoices', $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invoice_retrieval_returns_customer_plan_and_lines(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $invoice = Invoice::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
        ]);
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id]);

        $this->getJson('/api/invoices/'.$invoice->id.'?merchant_id='.$merchant->id)
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonCount(1, 'data.invoice_lines')
            ->assertJsonPath('data.subscription.plan.id', $subscription->plan_id);
    }

    public function test_usage_read_is_tenant_scoped_and_date_filtered(): void
    {
        [$merchant, $customer, $subscription] = $this->tenant();
        $otherMerchant = Merchant::factory()->create();
        $date = Carbon::parse('2026-09-23');
        DailyUsage::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => $date,
            'quantity' => 75,
        ]);

        $this->getJson('/api/customers/'.$customer->id.'/usage?merchant_id='.$otherMerchant->id)
            ->assertNotFound();
        $this->getJson('/api/customers/'.$customer->id.'/usage?merchant_id='.$merchant->id.'&date_from=2026-09-23&date_to=2026-09-23')
            ->assertOk()
            ->assertJsonPath('data.usage.0.quantity', 75);
    }

    private function tenant(): array
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        $plan = Plan::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-01 00:00:00',
        ]);

        return [$merchant, $customer, $subscription];
    }

    private function usagePayload(Merchant $merchant, Customer $customer, Subscription $subscription): array
    {
        return [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'occurred_at' => '2026-09-23 12:00:00',
            'quantity' => 150,
            'idempotency_key' => 'usage-'.uniqid(),
            'metadata' => ['source' => 'test'],
        ];
    }

    private function usageAttributes(Merchant $merchant, Customer $customer, Subscription $subscription, int $quantity, Carbon $date, string $key): array
    {
        return [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'occurred_at' => $date->copy()->setTime(12, 0, 0)->toDateTimeString(),
            'quantity' => $quantity,
            'idempotency_key' => $key,
        ];
    }
}
