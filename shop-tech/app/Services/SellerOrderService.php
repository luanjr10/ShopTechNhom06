<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SellerOrderService
{
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

    public function markDelivered(SellerOrder $sellerOrder): SellerOrder
    {
        $this->assertTransition($sellerOrder->status, 'delivered');

        return DB::transaction(function () use ($sellerOrder) {
            $sellerOrder->update(['status' => 'delivered']);
            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh();
        });
    }

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
                $this->walletService->release($wallet, $sellerAmount, 'seller_order', $sellerOrder->id);
            }

            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh();
        });
    }

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
                Log::warning('markDeliveredByDriver: SO đang shipping nhưng không có shipment', [
                    'seller_order_id' => $sellerOrder->id,
                ]);
            }

            $sellerOrder->update(['status' => 'delivered']);
            $this->syncParentOrderStatus($sellerOrder);

            return $sellerOrder->refresh()->load('shipment');
        });
    }

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
