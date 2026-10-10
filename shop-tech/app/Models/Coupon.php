<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'new_customer_only',
        'weekday',
        'daily_limit',
        'user_id',
        'trade_in_request_id',
        'store_id',
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
        'new_customer_only' => 'boolean',
        'weekday' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CouponClaim::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isTierRestricted(): bool
    {
        return $this->target_tier !== null;
    }

    public function isDaily(): bool
    {
        return $this->weekday !== null;
    }

    public function isSoldOutToday(): bool
    {
        if (! $this->isDaily() || $this->daily_limit === null) {
            return false;
        }

        return $this->redemptions()
            ->where('created_at', '>=', now('Asia/Ho_Chi_Minh')->startOfDay())
            ->count() >= $this->daily_limit;
    }

    public static function phoneHasOrdered(string $phone): bool
    {
        return Order::where('receiver_phone', $phone)->where('status', '!=', 'cancelled')->exists();
    }

    public static function todayWeekday(): int
    {
        return now('Asia/Ho_Chi_Minh')->dayOfWeek;
    }

    public static function userIsNewCustomer(int $userId): bool
    {
        return ! Order::where('user_id', $userId)->where('status', '!=', 'cancelled')->exists();
    }
}
