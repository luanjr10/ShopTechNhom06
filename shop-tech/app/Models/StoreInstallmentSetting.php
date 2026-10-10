<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInstallmentSetting extends Model
{
    /** @var list<array{months: int, monthly_rate: float}> */
    public const DEFAULT_TERMS = [
        ['months' => 3, 'monthly_rate' => 0.0],
        ['months' => 6, 'monthly_rate' => 0.8],
        ['months' => 12, 'monthly_rate' => 1.2],
    ];

    public const DEFAULT_MIN_ORDER = 3000000;

    protected $fillable = [
        'store_id',
        'enabled',
        'min_order',
        'terms',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'min_order' => 'decimal:2',
        'terms' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
