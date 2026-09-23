<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MerchantDashboardService
{
    public function forMerchant(Merchant $merchant): array
    {
        $now = CarbonImmutable::now();
        $activeSubscriptions = Subscription::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->with(['customer', 'plan'])
            ->get();

        $cycles = $activeSubscriptions->mapWithKeys(fn (Subscription $subscription) => [
            $subscription->id => $this->cycleFor($subscription, $now),
        ]);

        return [
            'merchant' => $merchant,
            'cycle' => $this->displayCycle($activeSubscriptions, $cycles, $now),
            'projected_overage' => $this->projectedOverage($activeSubscriptions, $cycles),
            'top_customers' => $this->topCustomers($merchant, $now),
            'usage_drops' => $this->usageDrops($merchant, $now),
        ];
    }

    private function cycleFor(Subscription $subscription, CarbonImmutable $now): array
    {
        $start = CarbonImmutable::parse($subscription->starts_at);
        $interval = $subscription->plan->billing_interval;
        $unit = $interval === 'yearly' ? 'year' : 'month';
        $cycleStart = $start;

        while ($cycleStart->add(1, $unit)->lte($now)) {
            $cycleStart = $cycleStart->add(1, $unit);
        }

        return [
            'start' => $cycleStart->startOfDay(),
            'end' => $cycleStart->add(1, $unit)->startOfDay(),
        ];
    }

    private function displayCycle(Collection $subscriptions, Collection $cycles, CarbonImmutable $now): array
    {
        $cycle = $subscriptions->first()
            ? $cycles->get($subscriptions->first()->id)
            : ['start' => $now->startOfMonth(), 'end' => $now->startOfMonth()->addMonth()];
        $today = $now->startOfDay();
        $start = $cycle['start'];
        $end = $cycle['end'];

        return [
            'start' => $start,
            'end' => $end->subDay(),
            'days_elapsed' => max(0, $start->diffInDays($today) + 1),
            'days_remaining' => max(0, $today->diffInDays($end->subDay())),
        ];
    }

    private function projectedOverage(Collection $subscriptions, Collection $cycles): string
    {
        if ($subscriptions->isEmpty()) {
            return '0.00';
        }

        $start = $cycles->min(fn (array $cycle) => $cycle['start']);
        $end = $cycles->max(fn (array $cycle) => $cycle['end']);
        $usageRows = DB::table('daily_usage')
            ->where('merchant_id', $subscriptions->first()->merchant_id)
            ->whereIn('subscription_id', $subscriptions->pluck('id'))
            ->whereBetween('usage_date', [$start->toDateString(), $end->subDay()->toDateString()])
            ->select('subscription_id', 'usage_date', 'quantity')
            ->get();

        $totalCents = 0;
        foreach ($subscriptions as $subscription) {
            $cycle = $cycles->get($subscription->id);
            $usage = (int) $usageRows
                ->where('subscription_id', $subscription->id)
                ->filter(fn ($row) => $row->usage_date >= $cycle['start']->toDateString() && $row->usage_date < $cycle['end']->toDateString())
                ->sum('quantity');
            $overage = max(0, $usage - (int) $subscription->plan->included_units);
            $totalCents += $overage * $this->toCents($subscription->plan->overage_unit_price ?? '0.00');
        }

        return $this->fromCents($totalCents);
    }

    private function topCustomers(Merchant $merchant, CarbonImmutable $now): Collection
    {
        return DB::table('daily_usage')
            ->join('customers', function ($join) use ($merchant) {
                $join->on('customers.id', '=', 'daily_usage.customer_id')
                    ->where('customers.merchant_id', $merchant->id);
            })
            ->where('daily_usage.merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()])
            ->select('customers.id', 'customers.name', DB::raw('SUM(daily_usage.quantity) as usage_total'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('usage_total')
            ->limit(5)
            ->get();
    }

    private function usageDrops(Merchant $merchant, CarbonImmutable $now): Collection
    {
        $currentStart = $now->startOfMonth();
        $currentEnd = $now->startOfDay();
        $elapsedDays = $currentStart->diffInDays($currentEnd);
        $previousStart = $currentStart->subMonth();
        $previousEnd = $previousStart->addDays($elapsedDays);

        $customers = $merchant->customers()->select('id', 'name')->get()->keyBy('id');
        $current = $this->usageByCustomer($merchant, $customers->keys(), $currentStart, $currentEnd);
        $previous = $this->usageByCustomer($merchant, $customers->keys(), $previousStart, $previousEnd);

        return $customers->map(function ($customer) use ($current, $previous) {
            $previousUsage = (int) ($previous->get($customer->id) ?? 0);
            $currentUsage = (int) ($current->get($customer->id) ?? 0);

            if ($previousUsage === 0 || $currentUsage * 2 >= $previousUsage) {
                return null;
            }

            return (object) [
                'name' => $customer->name,
                'previous_usage' => $previousUsage,
                'current_usage' => $currentUsage,
                'change_percent' => (int) round((($currentUsage - $previousUsage) / $previousUsage) * 100),
            ];
        })->filter()->sortBy('change_percent')->values();
    }

    private function usageByCustomer(Merchant $merchant, Collection $customerIds, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return DB::table('daily_usage')
            ->where('merchant_id', $merchant->id)
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('usage_date', [$start->toDateString(), $end->toDateString()])
            ->select('customer_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('customer_id')
            ->pluck('quantity', 'customer_id');
    }

    private function toCents(string|int $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim((string) $amount), 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
