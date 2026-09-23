<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function create(array $attributes): Subscription
    {
        $customer = Customer::query()
            ->whereKey($attributes['customer_id'])
            ->where('merchant_id', $attributes['merchant_id'])
            ->firstOrFail();
        $plan = Plan::query()
            ->whereKey($attributes['plan_id'])
            ->where('merchant_id', $attributes['merchant_id'])
            ->where('active', true)
            ->firstOrFail();

        $startsAt = $attributes['starts_at'];
        $endsAt = $attributes['ends_at'] ?? null;
        $overlap = $customer->subscriptions()
            ->where('status', 'active')
            ->where('starts_at', '<', $endsAt ?? '9999-12-31 23:59:59')
            ->where(function ($query) use ($startsAt) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'The customer already has an overlapping active subscription.',
            ]);
        }

        return DB::transaction(fn () => Subscription::create([
            ...$attributes,
            'status' => $attributes['status'] ?? 'active',
        ]));
    }

    public function activate(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'active']);

        return $subscription->refresh();
    }

    public function cancel(Subscription $subscription, ?string $endsAt = null): Subscription
    {
        $subscription->update([
            'status' => 'cancelled',
            'ends_at' => $endsAt ?? now(),
        ]);

        return $subscription->refresh();
    }
}
