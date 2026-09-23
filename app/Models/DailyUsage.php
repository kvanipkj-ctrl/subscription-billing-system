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
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'quantity' => 'integer',
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
