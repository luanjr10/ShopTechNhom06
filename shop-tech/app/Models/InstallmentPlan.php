<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'store_id',
        'seller_order_id',
        'decided_at',
        'decision_note',
        'principal',
        'months',
        'monthly_rate',
        'total_interest',
        'total_payable',
        'status',
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'months' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class, 'plan_id')->orderBy('number');
    }
}
