<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use Carbon\Carbon;

/**
 * Mọi sản phẩm trên sàn bảo hành 1 năm, tính từ THỜI ĐIỂM MUA HÀNG
 * (Order::paid_at — lúc khách thanh toán xong; fallback created_at nếu vì lý
 * do gì đó paid_at chưa có, VD đơn COD cũ). Chỉ áp dụng khi seller_order đã
 * 'completed' (khách đã xác nhận NHẬN hàng) — chưa nhận hàng thì chưa có gì
 * để bảo hành.
 */
class WarrantyService
{
    public const WARRANTY_MONTHS = 12;

    /**
     * @return array{applicable: bool, status: string, label: string, expires_at: ?Carbon, purchased_at: ?Carbon}
     */
    public function statusForSellerOrder(SellerOrder $sellerOrder, ?Order $order = null): array
    {
        $order ??= $sellerOrder->order;

        if ($sellerOrder->status !== 'completed' || ! $order) {
            return [
                'applicable' => false,
                'status' => 'chua_ap_dung',
                'label' => 'Chưa áp dụng (đơn chưa hoàn tất)',
                'expires_at' => null,
                'purchased_at' => null,
            ];
        }

        $purchasedAt = $order->paid_at ?? $order->created_at;
        $expiresAt = $purchasedAt->copy()->addMonths(self::WARRANTY_MONTHS);
        $covered = $expiresAt->isFuture();

        return [
            'applicable' => true,
            'status' => $covered ? 'con_han' : 'het_han',
            'label' => $covered ? 'Còn hạn bảo hành' : 'Hết hạn bảo hành',
            'expires_at' => $expiresAt,
            'purchased_at' => $purchasedAt,
        ];
    }

    /** true nếu khách được phép gửi yêu cầu hoàn trả/bảo hành cho seller_order này. */
    public function canRequestReturn(SellerOrder $sellerOrder): bool
    {
        return $sellerOrder->status === 'completed';
    }
}
