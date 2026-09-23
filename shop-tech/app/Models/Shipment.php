<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $fillable = [
        'seller_order_id',
        'provider',
        'tracking_number',
        'client_order_code',
        'service_type_id',
        'fee',
        'cod_amount',
        'cod_transferred_at',
        'weight_gram',
        'length_cm',
        'width_cm',
        'height_cm',
        'status',
        'expected_delivery_time',
        'provider_response',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'provider_response' => 'array',
        'expected_delivery_time' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cod_transferred_at' => 'datetime',
    ];

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }
}
