<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Store extends Model
{
    protected $fillable = [
        'seller_profile_id',
        'name',
        'slug',
        'logo',
        'description',
        'status',
        'pickup_contact_name',
        'pickup_phone',
        'province_id',
        'province_name',
        'district_id',
        'district_name',
        'ward_code',
        'ward_name',
        'address_line',
    ];

    /**
     * Store đã khai đủ địa chỉ lấy hàng GHN chưa (dùng khi seller bàn giao vận
     * chuyển — SellerOrderService::handover() chặn nếu thiếu).
     */
    public function hasPickupAddress(): bool
    {
        return $this->district_id !== null && $this->ward_code !== null;
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(StoreFollow::class);
    }

    /** Đánh giá sao của TẤT CẢ sản phẩm thuộc gian hàng — dùng tính rating trung bình hàng loạt (withAvg). */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(ProductReview::class, Product::class);
    }
}
