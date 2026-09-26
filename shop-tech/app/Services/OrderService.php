<?php

namespace App\Services;

use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(
        private CommissionService $commissionService,
        private ShippingService $shippingService,
        private CouponService $couponService,
    ) {}

    public function place(User $user, array $items, array $shipping): Order
    {
        if (empty($items)) {
            throw new RuntimeException('Giỏ hàng trống');
        }

        return DB::transaction(function () use ($user, $items, $shipping) {
            $productIds = collect($items)->pluck('product_id')->unique()->all();
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $specs = ProductSpecification::whereIn('productId', $productIds)->get()->keyBy('productId');

            $groups = [];

            foreach ($items as $line) {
                $product = $products->get($line['product_id']);

                if (! $product) {
                    throw new RuntimeException("Sản phẩm #{$line['product_id']} không tồn tại");
                }
                if (! $product->store_id) {
                    throw new RuntimeException("Sản phẩm '{$product->name}' không thuộc gian hàng nào");
                }

                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $sku = $line['sku'] ?? null;
                $spec = $specs->get($product->id);
                $unitPrice = ProductPricingService::unitPrice($product, $spec, $sku);
                $availableStock = ProductPricingService::stock($product, $spec, $sku);

                if ($quantity > $availableStock) {
                    throw new RuntimeException("Sản phẩm '{$product->name}' chỉ còn {$availableStock} trong kho");
                }

                $groups[$product->store_id][] = [
                    'product' => $product,
                    'sku' => $sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => round($unitPrice * $quantity, 2),
                    'category_id' => $product->category_id,
                ];
            }

            $productsSubtotal = collect($groups)->flatten(1)->sum('line_total');

            $shippingQuote = $this->shippingService->quoteCart(
                array_map(fn ($line) => ['product_id' => $line['product_id'], 'quantity' => max(1, (int) ($line['quantity'] ?? 1))], $items),
                (int) $shipping['district_id'],
                (string) $shipping['ward_code'],
                isset($shipping['province_id']) ? (int) $shipping['province_id'] : null,
            );
            $shippingFeeByStore = collect($shippingQuote['by_store'])->keyBy('store_id');
            $totalShippingFee = (float) $shippingQuote['total_fee'];

            $discountAmount = 0.0;
            $coupon = null;
            $couponCode = $shipping['coupon_code'] ?? null;
            if ($couponCode) {
                $applied = $this->couponService->apply($couponCode, (float) $productsSubtotal, $user, $totalShippingFee);
                $coupon = $applied['coupon'];
                $discountAmount = $applied['discount_amount'];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => 0,
                'shipping_fee' => $totalShippingFee,
                'expected_delivery_time' => $shippingQuote['expected_delivery_time'],
                'payment_method' => $shipping['payment_method'],
                'discount_code' => $coupon?->code,
                'discount_amount' => $discountAmount,
                'receiver_name' => $shipping['receiver_name'],
                'receiver_phone' => $shipping['receiver_phone'],
                'shipping_address' => $shipping['shipping_address'],
                'ghn_province_id' => $shipping['province_id'],
                'ghn_province_name' => $shipping['province_name'],
                'ghn_district_id' => $shipping['district_id'],
                'ghn_district_name' => $shipping['district_name'],
                'ghn_ward_code' => $shipping['ward_code'],
                'ghn_ward_name' => $shipping['ward_name'],
            ]);

            if ($coupon) {
                $coupon->increment('used_count');
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                ]);
            }

            $orderTotal = 0;

            foreach ($groups as $storeId => $lines) {
                $store = $lines[0]['product']->store;
                $store = $store ?: Store::findOrFail($storeId);

                $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);

                $categoryIds = array_unique(array_column($lines, 'category_id'));
                $categoryId = count($categoryIds) === 1 ? $categoryIds[0] : null;
                $rate = $this->commissionService->resolveRate($store, $categoryId);

                $sellerOrder = SellerOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                    'seller_profile_id' => $store->seller_profile_id,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'shipping_fee' => (float) ($shippingFeeByStore->get($storeId)['fee'] ?? 0),
                    'commission_rate' => $rate,
                    'commission_amount' => 0,
                    'seller_amount' => 0,
                ]);

                foreach ($lines as $line) {
                    $sellerOrder->items()->create([
                        'product_id' => $line['product']->id,
                        'product_name' => $line['product']->name,
                        'sku' => $line['sku'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                    ]);
                }

                $orderTotal += $subtotal;
            }

            $grandTotal = max(0, round($orderTotal + $totalShippingFee - $discountAmount, 2));
            $order->update(['total_amount' => $grandTotal]);

            return $order->load('sellerOrders.items');
        });
    }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $sellerOrders = $order->sellerOrders()->with('items')->lockForUpdate()->get();

            if ($sellerOrders->isEmpty() || $sellerOrders->contains(fn (SellerOrder $so) => $so->status !== 'pending')) {
                throw new RuntimeException('Đơn hàng đã được xác nhận hoặc đang giao, không thể hủy.');
            }

            foreach ($sellerOrders as $sellerOrder) {
                $sellerOrder->update(['status' => 'cancelled']);
            }

            $order->update(['status' => 'cancelled']);

            return $order->refresh()->load('sellerOrders.items');
        });
    }
}
