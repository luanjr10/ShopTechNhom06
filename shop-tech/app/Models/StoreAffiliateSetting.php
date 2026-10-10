<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAffiliateSetting extends Model
{
    public const DEFAULT_RATE = 2.0;

    public const DEFAULT_HOLD_DAYS = 7;

    public const DEFAULT_MIN_WITHDRAWAL = 50000;

    protected $fillable = [
        'store_id',
        'enabled',
        'rate',
        'hold_days',
        'min_withdrawal',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'rate' => 'decimal:2',
        'hold_days' => 'integer',
        'min_withdrawal' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
