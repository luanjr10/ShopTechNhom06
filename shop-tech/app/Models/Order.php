<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
        'shipping_fee',
        'expected_delivery_time',
        'payment_method',
        'discount_code',
        'discount_amount',
        'payment_ref',
        'sepay_invoice_id',
        'sepay_transaction_id',
        'paid_at',
        'receiver_name',
        'receiver_phone',
        'shipping_address',
        'ghn_province_id',
        'ghn_province_name',
        'ghn_district_id',
        'ghn_district_name',
        'ghn_ward_code',
        'ghn_ward_name',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'expected_delivery_time' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }
}
