<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generate(int $merchantId, Subscription $subscription, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        if ((int) $subscription->merchant_id !== $merchantId) {
            abort(404);
        }

        $plan = $subscription->plan;
        $usage = (int) $subscription->usageEvents()
            ->where('occurred_at', '>=', $periodStart)
            ->where('occurred_at', '<', $periodEnd)
            ->sum('quantity');
        $included = (int) $plan->included_units;
        $overage = max(0, $usage - $included);
        $baseCents = $this->toCents($plan->base_price);
        $overageUnitCents = $this->toCents($plan->overage_unit_price ?? '0.00');
        $overageCents = $overage * $overageUnitCents;
        $subtotal = $this->fromCents($baseCents + $overageCents);

        try {
            return [
                'invoice' => DB::transaction(function () use ($merchantId, $subscription, $periodStart, $periodEnd, $baseCents, $overage, $overageUnitCents, $overageCents, $subtotal, $plan): Invoice {
                $invoice = Invoice::create([
                    'merchant_id' => $merchantId,
                    'customer_id' => $subscription->customer_id,
                    'subscription_id' => $subscription->id,
                    'billing_period_start' => $periodStart,
                    'billing_period_end' => $periodEnd,
                    'status' => 'issued',
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'currency' => $plan->currency,
                    'issued_at' => now(),
                ]);

                $invoice->invoiceLines()->createMany([
                    [
                        'description' => $plan->name.' base charge',
                        'quantity' => 1,
                        'unit_price' => $this->fromCents($baseCents),
                        'amount' => $this->fromCents($baseCents),
                        'type' => 'base',
                    ],
                    ...($overage > 0 ? [[
                        'description' => 'Usage overage',
                        'quantity' => $overage,
                        'unit_price' => $this->fromCents($overageUnitCents),
                        'amount' => $this->fromCents($overageCents),
                        'type' => 'overage',
                    ]] : []),
                ]);

                return $invoice->load('invoiceLines');
                }),
                'created' => true,
            ];
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            return [
                'invoice' => Invoice::query()
                ->where('merchant_id', $merchantId)
                ->where('subscription_id', $subscription->id)
                ->where('billing_period_start', $periodStart)
                ->where('billing_period_end', $periodEnd)
                ->with('invoiceLines')
                ->firstOrFail(),
                'created' => false,
            ];
        }
    }

    private function toCents(string|int $amount): int
    {
        $normalized = trim((string) $amount);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return $exception->getCode() === '23000' && (
            str_contains($message, 'duplicate') ||
            str_contains($message, 'unique constraint failed')
        ) || $exception instanceof UniqueConstraintViolationException;
    }
}
