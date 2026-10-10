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
        private XuService $xuService,
        private InstallmentService $installmentService,
        private TradeInService $tradeInService,
        private AffiliateService $affiliateService,
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
                    'affiliate' => $this->affiliateService->attribute($user, $product, $line['aff_code'] ?? null),
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

            $rateByStore = [];
            $commissionGroups = [];
            foreach ($groups as $storeId => $lines) {
                $store = $lines[0]['product']->store ?: Store::findOrFail($storeId);
                $categoryIds = array_unique(array_column($lines, 'category_id'));
                $categoryId = count($categoryIds) === 1 ? $categoryIds[0] : null;
                $rateByStore[$storeId] = $this->commissionService->resolveRate($store, $categoryId);
                $commissionGroups[$storeId] = [
                    'store' => $store,
                    'subtotal' => (float) array_sum(array_column($lines, 'line_total')),
                    'category_id' => $categoryId,
                ];
            }

            $commissionTotal = $this->commissionService->estimateTotal($commissionGroups);
            $discountAmount = 0.0;
            $coupon = null;
            $couponCode = $shipping['coupon_code'] ?? null;
            if ($couponCode) {
                $applied = $this->couponService->apply(
                    $couponCode,
                    (float) $productsSubtotal,
                    $user,
                    $totalShippingFee,
                    $commissionTotal,
                    $shipping['receiver_phone'] ?? null,
                    array_map(fn (array $group) => $group['subtotal'], $commissionGroups),
                    $shippingFeeByStore->map(fn (array $row) => (float) ($row['fee'] ?? 0))->all(),
                );
                $coupon = $applied['coupon'];
                $discountAmount = $applied['discount_amount'];
            }

            $isStoreFunded = $coupon !== null && $coupon->store_id !== null;
            $platformDiscount = $isStoreFunded ? 0.0 : $discountAmount;

            $xuUsed = 0;
            if (! empty($shipping['use_xu'])) {
                $productDiscount = ($coupon && ! $coupon->is_free_ship) ? $discountAmount : 0.0;
                $xuUsed = $this->xuService->maxRedeemable(
                    $user,
                    (float) $productsSubtotal - $productDiscount,
                    $commissionTotal - $platformDiscount,
                );
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
                'xu_used' => $xuUsed,
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
                $this->tradeInService->markUsedByOrder($coupon, $order);
            }

            if ($xuUsed > 0) {
                $this->xuService->spendOnOrder($user, $order, $xuUsed);
            }

            $orderTotal = 0;

            foreach ($groups as $storeId => $lines) {
                $store = $commissionGroups[$storeId]['store'];

                $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);

                $rate = $rateByStore[$storeId];

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
                    'store_discount' => ($isStoreFunded && (int) $storeId === (int) $coupon->store_id && ! $coupon->is_free_ship) ? $discountAmount : 0,
                    'store_shipping_subsidy' => ($isStoreFunded && (int) $storeId === (int) $coupon->store_id && $coupon->is_free_ship) ? $discountAmount : 0,
                ]);

                foreach ($lines as $line) {
                    $sellerOrder->items()->create([
                        'product_id' => $line['product']->id,
                        'product_name' => $line['product']->name,
                        'sku' => $line['sku'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                        'affiliate_referrer_id' => $line['affiliate']['referrer_id'] ?? null,
                        'affiliate_rate' => $line['affiliate']['rate'] ?? null,
                    ]);
                }

                $orderTotal += $subtotal;
            }

            $grandTotal = max(0, round($orderTotal + $totalShippingFee - $discountAmount - $xuUsed, 2));
            $order->update(['total_amount' => $grandTotal]);

            if ($shipping['payment_method'] === 'installment') {
                $this->installmentService->createRequest($order, $user, (int) ($shipping['installment_months'] ?? 0));
            }

            return $order->load('sellerOrders.items', 'installmentPlan.payments');
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
            $this->xuService->refundOrder($order);
            $this->installmentService->cancelForOrder($order);
            $this->tradeInService->releaseForOrder($order);

            return $order->refresh()->load('sellerOrders.items');
        });
    }
}
