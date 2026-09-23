<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'plan_id',
        'previous_subscription_id',
        'starts_at',
        'ends_at',
        'status',
        'base_price_cents',
        'included_units',
        'overage_rate_cents',
        'currency',
        'billing_cycle',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'base_price_cents' => 'integer',
            'included_units' => 'integer',
            'overage_rate_cents' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function previousSubscription(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_subscription_id');
    }

    public function nextSubscriptions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_subscription_id');
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
