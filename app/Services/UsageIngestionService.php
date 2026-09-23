<?php

namespace App\Services;

use App\Exceptions\UsageIdempotencyConflict;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\UsageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UsageIngestionService
{
    /**
     * The database unique key is the final protection against concurrent duplicates.
     * A reused key with different payload data is a conflict, not a new event.
     *
     * @return array{event: UsageEvent, created: bool}
     */
    public function ingest(array $attributes): array
    {
        $occurredAt = CarbonImmutable::parse($attributes['occurred_at']);
        $metadata = $attributes['metadata'] ?? null;

        $customer = Customer::query()
            ->whereKey($attributes['customer_id'])
            ->where('merchant_id', $attributes['merchant_id'])
            ->firstOrFail();

        $subscription = null;
        if (! empty($attributes['subscription_id'])) {
            $subscription = Subscription::query()
                ->whereKey($attributes['subscription_id'])
                ->where('merchant_id', $attributes['merchant_id'])
                ->where('customer_id', $customer->id)
                ->firstOrFail();

            if ($occurredAt->lt($subscription->starts_at) ||
                ($subscription->ends_at && $occurredAt->gte($subscription->ends_at))) {
                abort(422, 'The usage event is outside the subscription period.');
            }
        }

        $payload = [
            'merchant_id' => (int) $attributes['merchant_id'],
            'customer_id' => $customer->id,
            'subscription_id' => $subscription?->id,
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
            'quantity' => (int) $attributes['quantity'],
            'idempotency_key' => $attributes['idempotency_key'],
            'metadata' => $metadata,
        ];

        try {
            return DB::transaction(function () use ($payload): array {
                $event = UsageEvent::create($payload);

                return ['event' => $event, 'created' => true];
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $event = UsageEvent::query()
                ->where('merchant_id', $payload['merchant_id'])
                ->where('idempotency_key', $payload['idempotency_key'])
                ->firstOrFail();

            $existingPayload = [
                'merchant_id' => (int) $event->merchant_id,
                'customer_id' => (int) $event->customer_id,
                'subscription_id' => $event->subscription_id ? (int) $event->subscription_id : null,
                'occurred_at' => $event->occurred_at->format('Y-m-d H:i:s'),
                'quantity' => (int) $event->quantity,
                'idempotency_key' => $event->idempotency_key,
                'metadata' => $event->metadata,
            ];

            if ($this->canonical($existingPayload) !== $this->canonical($payload)) {
                throw new UsageIdempotencyConflict();
            }

            return ['event' => $event, 'created' => false];
        }
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        return $exception->getCode() === '23000' && str_contains(
            strtolower($exception->getMessage()),
            'duplicate'
        ) || $exception instanceof UniqueConstraintViolationException;
    }

    private function canonical(array $payload): string
    {
        return json_encode(Arr::sortRecursive($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
