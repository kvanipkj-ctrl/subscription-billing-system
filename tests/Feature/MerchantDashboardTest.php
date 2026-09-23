<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_merchant_and_billing_overview(): void
    {
        [$merchant] = $this->tenant();

        $this->get('/merchants/'.$merchant->id.'/dashboard')
            ->assertOk()
            ->assertSee('Subscription Billing Dashboard')
            ->assertSee($merchant->name)
            ->assertSee('Current billing cycle')
            ->assertSee('Projected overage revenue');
    }

    public function test_top_five_customers_are_ordered_and_limited(): void
    {
        [$merchant, $subscription] = $this->tenant();
        $customers = collect(range(1, 6))->map(function (int $index) use ($merchant) {
            return Customer::factory()->for($merchant)->create(['name' => 'Dashboard Customer '.$index]);
        });

        foreach ($customers as $index => $customer) {
            DailyUsage::factory()->create([
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'subscription_id' => $subscription->id,
                'usage_date' => now()->startOfMonth()->addDays(2),
                'quantity' => ($index + 1) * 100,
            ]);
        }

        $response = $this->get('/merchants/'.$merchant->id.'/dashboard')->assertOk();

        $response->assertSeeInOrder([
            'Dashboard Customer 6',
            'Dashboard Customer 5',
            'Dashboard Customer 4',
            'Dashboard Customer 3',
            'Dashboard Customer 2',
        ]);
        $response->assertDontSee('Dashboard Customer 1');
    }

    public function test_dashboard_projects_only_usage_above_included_units(): void
    {
        [$merchant, $subscription] = $this->tenant();
        $customer = Customer::factory()->for($merchant)->create();

        DailyUsage::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => now()->startOfMonth()->addDay(),
            'quantity' => 1200,
        ]);

        $this->get('/merchants/'.$merchant->id.'/dashboard')
            ->assertOk()
            ->assertSee('USD 10.00');
    }

    public function test_dashboard_displays_customers_with_more_than_fifty_percent_drop(): void
    {
        [$merchant, $subscription] = $this->tenant();
        $drop = Customer::factory()->for($merchant)->create(['name' => 'Declining Customer']);
        $stable = Customer::factory()->for($merchant)->create(['name' => 'Stable Customer']);
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $previousMonth = $currentMonth->subMonth();

        foreach ([
            [$drop, $previousMonth->addDays(2), 1000],
            [$drop, $currentMonth->addDays(2), 400],
            [$stable, $previousMonth->addDays(2), 1000],
            [$stable, $currentMonth->addDays(2), 600],
        ] as [$customer, $date, $quantity]) {
            DailyUsage::factory()->create([
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'subscription_id' => $subscription->id,
                'usage_date' => $date,
                'quantity' => $quantity,
            ]);
        }

        $this->get('/merchants/'.$merchant->id.'/dashboard')
            ->assertOk()
            ->assertSee('Declining Customer')
            ->assertSee('-60%')
            ->assertDontSee('-40%');
    }

    public function test_dashboard_handles_previous_month_zero_usage_without_false_drop(): void
    {
        [$merchant, $subscription] = $this->tenant();
        $customer = Customer::factory()->for($merchant)->create(['name' => 'New Usage Customer']);
        DailyUsage::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => now()->startOfMonth()->addDay(),
            'quantity' => 500,
        ]);

        $this->get('/merchants/'.$merchant->id.'/dashboard')
            ->assertOk()
            ->assertDontSee('-100%');
    }

    public function test_dashboard_is_tenant_isolated(): void
    {
        [$merchant, $subscription] = $this->tenant();
        [$otherMerchant, $otherSubscription] = $this->tenant('Other Merchant');
        $customer = Customer::factory()->for($merchant)->create(['name' => 'Visible Customer']);
        $otherCustomer = Customer::factory()->for($otherMerchant)->create(['name' => 'Hidden Customer']);

        foreach ([[$customer, $subscription, 100], [$otherCustomer, $otherSubscription, 9999]] as [$record, $recordSubscription, $quantity]) {
            DailyUsage::factory()->create([
                'merchant_id' => $recordSubscription->merchant_id,
                'customer_id' => $record->id,
                'subscription_id' => $recordSubscription->id,
                'usage_date' => now()->startOfMonth()->addDay(),
                'quantity' => $quantity,
            ]);
        }

        $this->get('/merchants/'.$merchant->id.'/dashboard')
            ->assertOk()
            ->assertSee('Visible Customer')
            ->assertDontSee('Hidden Customer')
            ->assertDontSee('9999');
    }

    public function test_missing_merchant_returns_not_found(): void
    {
        $this->get('/merchants/999999/dashboard')->assertNotFound();
    }

    private function tenant(string $name = 'Dashboard Merchant'): array
    {
        $merchant = Merchant::factory()->create(['name' => $name]);
        $plan = Plan::factory()->for($merchant)->create(['included_units' => 1000, 'overage_unit_price' => '0.05']);
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'starts_at' => CarbonImmutable::now()->startOfMonth(),
        ]);

        return [$merchant, $subscription, $customer];
    }
}
