<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOrder extends Model
{
    protected $fillable = [
        'order_id',
        'store_id',
        'seller_profile_id',
        'status',
        'subtotal',
        'shipping_fee',
        'commission_rate',
        'commission_amount',
        'seller_amount',
        'store_discount',
        'store_shipping_subsidy',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'seller_amount' => 'decimal:2',
        'store_discount' => 'decimal:2',
        'store_shipping_subsidy' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * Phần giảm giá hàng (voucher + xu) mà sàn đã chịu cho đơn con này, chia theo tỉ lệ giá trị hàng.
     * Voucher miễn phí ship không làm giảm giá trị hàng nên không tính.
     */
    public function discountShare(): float
    {
        $order = $this->order;
        $shared = 0.0;

        if ((float) $order->discount_amount > 0 && $order->discount_code) {
            $coupon = Coupon::where('code', $order->discount_code)->first(['is_free_ship', 'store_id']);

            // Voucher miễn ship không giảm giá hàng; voucher của gian hàng đã nằm riêng ở store_discount.
            if ($coupon && ! $coupon->is_free_ship && $coupon->store_id === null) {
                $shared += (float) $order->discount_amount;
            }
        }

        $shared += (float) $order->xu_used;

        $share = 0.0;
        if ($shared > 0) {
            $productsTotal = (float) $order->sellerOrders()->sum('subtotal');
            $share = $productsTotal > 0 ? round($shared * (float) $this->subtotal / $productsTotal, 2) : 0.0;
        }

        return round($share + (float) $this->store_discount, 2);
    }

    /**
     * Giá trị hàng tính hoa hồng và tiền người bán nhận: tạm tính trừ phần giảm do chính gian hàng chịu.
     */
    /**
     * Số tiền người bán thực nhận từ đơn: giá trị hàng (sau giảm giá do gian hàng chịu) trừ hoa hồng sàn,
     * trừ phần phí ship gian hàng hỗ trợ cho khách.
     */
    public function sellerNet(): float
    {
        $base = $this->storeBase();

        return round($base - round($base * (float) $this->commission_rate / 100, 2) - (float) $this->store_shipping_subsidy, 2);
    }

    public function storeBase(): float
    {
        return round((float) $this->subtotal - (float) $this->store_discount, 2);
    }

    public function netProductAmount(): float
    {
        return round((float) $this->subtotal - $this->discountShare(), 2);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
