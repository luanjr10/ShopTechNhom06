<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeInRequest extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'trade_in_model_id',
        'model_name',
        'condition',
        'has_box',
        'has_charger',
        'description',
        'images',
        'estimated_price',
        'final_price',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'coupon_code',
        'used_order_id',
    ];

    protected $casts = [
        'has_box' => 'boolean',
        'has_charger' => 'boolean',
        'images' => 'array',
        'estimated_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tradeInModel(): BelongsTo
    {
        return $this->belongsTo(TradeInModel::class);
    }
}
