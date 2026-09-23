<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsage extends Model
{
    use HasFactory;

    protected $table = 'daily_usage';

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'usage_date',
        'event_count',
        'total_units',
        'last_event_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'event_count' => 'integer',
            'total_units' => 'integer',
            'last_event_at' => 'datetime',
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
}
