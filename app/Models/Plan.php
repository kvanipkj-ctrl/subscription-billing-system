<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'base_price_cents',
        'currency',
        'billing_cycle',
        'included_units',
        'overage_rate_cents',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price_cents' => 'integer',
            'included_units' => 'integer',
            'overage_rate_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
