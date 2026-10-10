<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAffiliate extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'rate',
        'enabled',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'enabled' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
