<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Chuyển trạng thái SellerOrder và xử lý ví/hoa hồng/vận chuyển/kho đi kèm.
 *
 * QUYẾT ĐỊNH 2026-09-19: seller TỰ giao hàng (không thuê GHN thật nữa) — GHN
 * sandbox chỉ còn dùng để ƯỚC TÍNH phí/ETA lúc đặt hàng (xem ShippingService::
 * quoteCart, KHÔNG đổi). handover() không gọi API tạo vận đơn nữa (sandbox
 * GHN giới hạn 3 đơn/nhiều lỗi dữ liệu test, chặn luồng seller); Shipment vẫn
 * được tạo (provider='self') để lưu phí/COD/trạng thái, chỉ là không có
 * tracking_number của hãng vận chuyển ngoài.
 * - shipping: seller bấm "Bàn giao vận chuyển" (handover()) — không side-effect
 *   gọi API ngoài nữa nhưng vẫn tách riêng khỏi updateStatus() vì tạo Shipment,
 *   trừ kho THẬT và giữ tiền vào ví (pending) — pending/confirmed hoàn toàn
 *   KHÔNG đụng tới kho/ví (xem OrderService::place, đã bỏ trừ kho/giữ ví lúc
 *   đặt hàng).
 * - delivered/completed từ 'shipping': seller (tự làm shipper) tự báo kết quả
 *   giao hàng qua markDeliveredByDriver()/markCancelledByDriver() — ĐÂY LÀ LUỒNG
 *   CHUẨN, không còn là chế độ test (tên method giữ nguyên để đỡ phải sửa
 *   route/FE, không còn gate theo APP_DRIVER_MODE). markDeliveredByDriver()
 *   CHỈ chuyển 'shipping' -> 'delivered' — KHÔNG phân biệt COD/online, ví
 *   luôn đứng ở "đang chờ" tới khi khách tự xác nhận (complete()) mới release
 *   sang "có thể rút" — xem doc-block của method.
 * - markDelivered()/markDeliveryFailed(): dự phòng cho GHN webhook nếu sau này
 *   quay lại dùng vận chuyển thật — hiện không có shipment nào mang
 *   tracking_number nên webhook sẽ không khớp được gì.
 * - completed: chốt commission_amount/seller_amount theo rate snapshot, chuyển
 *   ví pending -> withdrawable.
 * - cancelled: hoàn kho (products.stock/variant) + hoàn phần đã giữ trong ví
 *   (nếu chưa hoàn thành).
 */
class SellerOrderService
{
    /** Các bước chuyển trạng thái hợp lệ. */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['delivered', 'cancelled', 'completed'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private WalletService $walletService,
        private StockService $stockService,
    ) {}

    /**
     * Dùng cho các bước KHÔNG có side-effect đặc biệt: seller xác nhận
     * (pending->confirmed) hoặc hủy (->cancelled). `shipping`/`delivered`/
     * `completed` có action riêng (handover/markDelivered/complete) vì cần gọi
     * API ngoài hoặc yêu cầu đúng actor (chỉ webhook GHN / chỉ customer).
     */
    public function updateStatus(SellerOrder $sellerOrder, string $newStatus): SellerOrder
    {
        if (! in_array($newStatus, ['confirmed', 'cancelled'], true)) {
            throw new RuntimeException("Không thể tự chuyển trạng thái sang '{$newStatus}' qua thao tác này.");
        }

        $this->assertTransition($sellerOrder->status, $newStatus);

        return DB::transaction(function () use ($sellerOrder, $newStatus) {
            if ($newStatus === 'cancelled') {
                $this->cancel($sellerOrder);
            } else {
                $sellerOrder->update(['status' => $newStatus]);
            }

            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh();
        });
    }

    /**
     * Seller bấm "Bàn giao vận chuyển" — chỉ hợp lệ từ `confirmed`. KHÔNG gọi
     * GHN nữa (seller tự giao) — chỉ ghi nhận Shipment nội bộ (phí/COD đã biết
     * từ lúc đặt hàng) rồi chuyển seller_order sang `shipping`.
     *
     * QUYẾT ĐỊNH 2026-09-19: đây là nơi DUY NHẤT trừ kho thật (products.stock/
     * variant) VÀ giữ tiền vào ví (pending) — không còn trừ kho/giữ ví lúc đặt
     * hàng nữa (xem OrderService::place). Trước lúc bàn giao, seller_order còn
     * 'pending'/'confirmed' thì "Kho hàng" và "Ví" đều KHÔNG được đụng tới,
     * chỉ khi hàng thật sự rời kho (bàn giao) mới trừ kho + giữ tiền. Ném lỗi
     * (không đủ hàng) sẽ rollback toàn bộ transaction, seller_order giữ
     * nguyên 'confirmed'.
     */
    public function handover(SellerOrder $sellerOrder): SellerOrder
    {
        $this->assertTransition($sellerOrder->status, 'shipping');

        if ($sellerOrder->shipment) {
            throw new RuntimeException('Đơn hàng đã bàn giao vận chuyển rồi.');
        }

        return DB::transaction(function () use ($sellerOrder) {
            foreach ($sellerOrder->items as $item) {
                $this->stockService->decrement(
                    (int) $item->product_id,
                    $item->sku,
                    (int) $item->quantity,
                    $item->product_name,
                );
            }

            $wallet = $sellerOrder->sellerProfile->wallet;
            if ($wallet) {
                $rate = (float) $sellerOrder->commission_rate;
                $netAmount = round((float) $sellerOrder->subtotal * (1 - $rate / 100), 2);
                $this->walletService->hold($wallet, $netAmount, 'seller_order', $sellerOrder->id);
            }

            Shipment::create([
                'seller_order_id' => $sellerOrder->id,
                'provider' => 'self',
                'fee' => $sellerOrder->shipping_fee,
                'cod_amount' => $this->codAmountFor($sellerOrder),
                'status' => 'shipping',
                'expected_delivery_time' => $sellerOrder->order->expected_delivery_time,
                'shipped_at' => now(),
            ]);

            $sellerOrder->update(['status' => 'shipping']);
            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh()->load('shipment');
        });
    }

    /**
     * Số tiền seller cần thu hộ (COD) khi tự giao — chỉ áp dụng payment_method=
     * 'cod' (các phương thức online khách đã trả trước). Đơn multi-seller tách
     * COD theo tỷ lệ subtotal của từng seller_order, trừ phần giảm giá phân bổ
     * tương ứng, để tổng COD các gian hàng cộng lại đúng bằng order.total_amount.
     */
    private function codAmountFor(SellerOrder $sellerOrder): int
    {
        $order = $sellerOrder->order;
        if ($order->payment_method !== 'cod') {
            return 0;
        }

        $orderSubtotalTotal = (float) $order->total_amount - (float) $order->shipping_fee + (float) $order->discount_amount;
        $discountShare = $orderSubtotalTotal > 0
            ? round((float) $order->discount_amount * ((float) $sellerOrder->subtotal / $orderSubtotalTotal), 2)
            : 0.0;

        return max(0, (int) round((float) $sellerOrder->subtotal + (float) $sellerOrder->shipping_fee - $discountShare));
    }

    /**
     * GHN webhook báo Status=delivered — CHỈ nguồn tin cậy để set trạng thái này
     * (xem GhnWebhookController), không có route cho seller tự bấm.
     */
    public function markDelivered(SellerOrder $sellerOrder): SellerOrder
    {
        $this->assertTransition($sellerOrder->status, 'delivered');

        return DB::transaction(function () use ($sellerOrder) {
            $sellerOrder->update(['status' => 'delivered']);
            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh();
        });
    }

    /**
     * Customer xác nhận đã nhận hàng — CHỈ hợp lệ từ `delivered` (không cho
     * seller/webhook nhảy thẳng shipping->completed).
     */
    public function complete(SellerOrder $sellerOrder): SellerOrder
    {
        $this->assertTransition($sellerOrder->status, 'completed');

        return DB::transaction(function () use ($sellerOrder) {
            $rate = (float) $sellerOrder->commission_rate;
            $subtotal = (float) $sellerOrder->subtotal;
            $commission = round($subtotal * $rate / 100, 2);
            $sellerAmount = round($subtotal - $commission, 2);

            $sellerOrder->update([
                'status' => 'completed',
                'commission_amount' => $commission,
                'seller_amount' => $sellerAmount,
                'completed_at' => now(),
            ]);

            $wallet = $sellerOrder->sellerProfile->wallet;
            if ($wallet) {
                // Phần đã giữ (net) chuyển sang withdrawable.
                $this->walletService->release($wallet, $sellerAmount, 'seller_order', $sellerOrder->id);
            }

            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh();
        });
    }

    /**
     * GHN webhook báo trạng thái THẤT BẠI VĨNH VIỄN (returned/lost/damage/
     * scrap/exception/cancel — theo đúng "Mã trạng thái đơn hàng" GHN, các
     * trạng thái đánh dấu "Cuối"). CHỈ áp dụng khi seller_order đang 'shipping'
     * — hàng coi như không tới tay khách, hoàn kho + hoàn ví giống hủy đơn.
     * KHÔNG xử lý các trạng thái tạm thời (delivery_fail, waiting_to_return,
     * return, return_transporting...) — GHN có thể còn giao lại/đang xử lý.
     */
    public function markDeliveryFailed(SellerOrder $sellerOrder, string $ghnStatus): SellerOrder
    {
        $this->assertTransition($sellerOrder->status, 'cancelled');

        Log::warning('GHN giao hàng thất bại vĩnh viễn — tự hủy seller_order + hoàn kho/ví', [
            'seller_order_id' => $sellerOrder->id,
            'ghn_status' => $ghnStatus,
        ]);

        return DB::transaction(function () use ($sellerOrder) {
            $this->cancel($sellerOrder);
            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh();
        });
    }

    /**
     * Seller (tự đóng vai shipper) báo ĐÃ GIAO cho khách — LUỒNG CHUẨN, không
     * còn giới hạn theo APP_DRIVER_MODE. Tên method giữ nguyên "ByDriver" để
     * không phải sửa route/controller/FE.
     *
     * QUYẾT ĐỊNH 2026-09-19 (đồng bộ, không phân biệt COD/online nữa): "đã
     * giao" chỉ chuyển sang `delivered`, KHÔNG tự chốt `completed`/release ví
     * — tiền vẫn đứng ở ví "đang chờ" (sàn giữ hộ) cho tới khi CHÍNH KHÁCH tự
     * xác nhận đã nhận hàng (complete(), qua OrderController::
     * completeSellerOrder). Giữ đúng logic: chủ sàn cầm tiền, seller chỉ được
     * tạo yêu cầu rút khi tiền đã thật sự về "có thể rút". Nếu đơn bị hủy khi
     * còn 'shipping' (markCancelledByDriver/markDeliveryFailed), phần tiền
     * đang giữ này bị hoàn lại (reverseHold) chứ không mất — xem cancel().
     */
    public function markDeliveredByDriver(SellerOrder $sellerOrder): SellerOrder
    {
        $this->assertTransition($sellerOrder->status, 'delivered');

        return DB::transaction(function () use ($sellerOrder) {
            if ($sellerOrder->shipment) {
                $sellerOrder->shipment->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);
            } else {
                // Đơn shipping nhưng không có shipment = bất thường (không đi
                // qua handover()?). Cảnh báo, vẫn cho qua.
                Log::warning('markDeliveredByDriver: SO đang shipping nhưng không có shipment', [
                    'seller_order_id' => $sellerOrder->id,
                ]);
            }

            $sellerOrder->update(['status' => 'delivered']);
            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh()->load('shipment');
        });
    }

    /**
     * Seller (tự đóng vai shipper) báo giao THẤT BẠI / hủy — LUỒNG CHUẨN,
     * không còn giới hạn theo APP_DRIVER_MODE. Cho phép từ 'shipping' (đã bàn
     * giao) hoặc 'confirmed' (chưa bàn giao) — hoàn kho + reverseHold ví.
     */
    public function markCancelledByDriver(SellerOrder $sellerOrder): SellerOrder
    {
        if (! in_array($sellerOrder->status, ['confirmed', 'shipping'], true)) {
            throw new RuntimeException("Không thể hủy đơn từ trạng thái '{$sellerOrder->status}'.");
        }

        return DB::transaction(function () use ($sellerOrder) {
            if ($sellerOrder->shipment) {
                $sellerOrder->shipment->update(['status' => 'returned']);
            }

            $this->cancel($sellerOrder);
            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh()->load('shipment');
        });
    }

    private function assertTransition(string $current, string $newStatus): void
    {
        if (! in_array($newStatus, self::TRANSITIONS[$current] ?? [], true)) {
            throw new RuntimeException("Không thể chuyển trạng thái từ '{$current}' sang '{$newStatus}'");
        }
    }

    private function cancel(SellerOrder $sellerOrder): void
    {
        // Kho VÀ ví chỉ bị trừ/giữ lúc bàn giao (handover(), status 'shipping')
        // — hủy từ 'pending'/'confirmed' (chưa bàn giao) thì chưa có gì để
        // hoàn, hoàn nhầm sẽ CỘNG THÊM kho ảo/tiền ảo chưa từng bị trừ/giữ.
        if ($sellerOrder->status === 'shipping') {
            foreach ($sellerOrder->items as $item) {
                $this->stockService->restore((int) $item->product_id, $item->sku, (int) $item->quantity);
            }

            $rate = (float) $sellerOrder->commission_rate;
            $held = round((float) $sellerOrder->subtotal * (1 - $rate / 100), 2);

            $wallet = $sellerOrder->sellerProfile->wallet;
            if ($wallet) {
                $this->walletService->reverseHold($wallet, $held, 'seller_order', $sellerOrder->id);
            }
        }

        $sellerOrder->update(['status' => 'cancelled']);
    }

    /** Đồng bộ trạng thái Order cha khi các SellerOrder thay đổi. */
    private function syncParentOrderStatus(SellerOrder $sellerOrder): void
    {
        $order = $sellerOrder->order;
        $statuses = $order->sellerOrders()->pluck('status');

        if ($statuses->every(fn ($s) => $s === 'completed')) {
            $order->update(['status' => 'completed']);
        } elseif ($statuses->every(fn ($s) => $s === 'cancelled')) {
            $order->update(['status' => 'cancelled']);
        }
    }
}
