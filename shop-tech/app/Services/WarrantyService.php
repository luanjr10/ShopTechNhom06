<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use Carbon\Carbon;

class WarrantyService
{
    public const WARRANTY_MONTHS = 12;

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

    public function canRequestReturn(SellerOrder $sellerOrder): bool
    {
        return $sellerOrder->status === 'completed';
    }
}
