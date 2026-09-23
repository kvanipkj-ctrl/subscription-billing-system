<?php

namespace App\Services;

use App\Models\DailyUsage;
use App\Models\UsageEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DailyUsageAggregationService
{
    public function aggregateForDate(int $merchantId, int $customerId, int $subscriptionId, CarbonInterface $date): DailyUsage
    {
        $quantity = (int) UsageEvent::query()
            ->where('merchant_id', $merchantId)
            ->where('customer_id', $customerId)
            ->where('subscription_id', $subscriptionId)
            ->whereDate('occurred_at', $date)
            ->sum('quantity');

        return DB::transaction(function () use ($merchantId, $customerId, $subscriptionId, $date, $quantity): DailyUsage {
            DailyUsage::query()->upsert(
                [[
                    'merchant_id' => $merchantId,
                    'customer_id' => $customerId,
                    'subscription_id' => $subscriptionId,
                    'usage_date' => $date->toDateString(),
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['merchant_id', 'customer_id', 'subscription_id', 'usage_date'],
                ['quantity', 'updated_at']
            );

            return DailyUsage::query()
                ->where('merchant_id', $merchantId)
                ->where('customer_id', $customerId)
                ->where('subscription_id', $subscriptionId)
                ->whereDate('usage_date', $date)
                ->firstOrFail();
        });
    }
}
