<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'title',
        'description',
        'type',
        'target_tier',
        'is_free_ship',
        'value',
        'max_discount',
        'min_order_amount',
        'usage_limit',
        'per_user_limit',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_free_ship' => 'boolean',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(CouponClaim::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Voucher hạng thành viên — chỉ dành cho khách đạt target_tier trở lên. */
    public function isTierRestricted(): bool
    {
        return $this->target_tier !== null;
    }
}
