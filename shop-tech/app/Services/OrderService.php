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

/**
 * Tạo đơn multi-seller: gom item theo Store, tạo 1 Order cha + nhiều SellerOrder,
 * chốt commission_rate ngay lúc đặt (snapshot). KHÔNG trừ kho/giữ tiền ví ở
 * đây — cả 2 việc đó chỉ xảy ra lúc seller bàn giao vận chuyển (xem
 * SellerOrderService::handover()), vì lúc đặt hàng seller còn chưa xác nhận
 * gì, chưa có gì đảm bảo đơn sẽ được giao.
 */
class OrderService
{
    public function __construct(
        private CommissionService $commissionService,
        private ShippingService $shippingService,
        private CouponService $couponService,
    ) {}

    /**
     * @param  array<int, array{product_id:int, sku?:string|null, quantity:int}>  $items
     * @param  array{receiver_name:string, receiver_phone:string, shipping_address:string, province_id:int, province_name:string, district_id:int, district_name:string, ward_code:string, ward_name:string, payment_method:string, coupon_code?:string|null}  $shipping
     */
    public function place(User $user, array $items, array $shipping): Order
    {
        if (empty($items)) {
            throw new RuntimeException('Giỏ hàng trống');
        }

        return DB::transaction(function () use ($user, $items, $shipping) {
            // Lock từng dòng product NGAY TRONG transaction — chỉ để đọc số tồn kho
            // MỚI NHẤT lúc validate, KHÔNG trừ kho ở đây nữa (xem quyết định 2026-09-19
            // dưới đây). Không airtight chống race (2 đơn cùng pending có thể cùng
            // đi qua check này) nhưng đó là đánh đổi được chấp nhận để đổi lấy kho
            // luôn phản ánh đúng hàng THỰC SỰ đã rời kho.
            $productIds = collect($items)->pluck('product_id')->unique()->all();
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $specs = ProductSpecification::whereIn('productId', $productIds)->get()->keyBy('productId');

            $groups = []; // store_id => list of resolved lines

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

            // QUYẾT ĐỊNH 2026-09-19: kho (products.stock/variant) KHÔNG trừ lúc đặt
            // hàng nữa — chỉ trừ khi seller bàn giao vận chuyển (SellerOrderService::
            // handover(), lúc hàng thật sự rời kho). Trước đây trừ ngay ở đây khiến
            // "Kho hàng" giảm dù đơn còn đang chờ seller xác nhận. Cancel() (dưới)
            // vì vậy cũng không còn hoàn kho — vì stock chưa từng bị trừ.
            $productsSubtotal = collect($groups)->flatten(1)->sum('line_total');

            // Tính phí ship THẬT qua GHN, tự tính lại ở server — KHÔNG tin số FE
            // gửi lên (FE chỉ hiển thị số đã hỏi trước đó, không phải nguồn sự thật).
            // Lỗi ở đây (thiếu weight/dimension, store chưa có địa chỉ, GHN lỗi...)
            // sẽ ném RuntimeException, chặn đặt hàng luôn — KHÔNG fallback 0đ.
            $shippingQuote = $this->shippingService->quoteCart(
                array_map(fn ($line) => ['product_id' => $line['product_id'], 'quantity' => max(1, (int) ($line['quantity'] ?? 1))], $items),
                (int) $shipping['district_id'],
                (string) $shipping['ward_code'],
                isset($shipping['province_id']) ? (int) $shipping['province_id'] : null,
            );
            $shippingFeeByStore = collect($shippingQuote['by_store'])->keyBy('store_id');
            $totalShippingFee = (float) $shippingQuote['total_fee'];

            // Voucher giảm giá do sàn chịu — KHÔNG trừ vào subtotal/hoa hồng seller
            // (seller vẫn nhận đủ theo giá bán), chỉ giảm số tiền khách phải trả.
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
                $store = $lines[0]['product']->store; // đã eager? load quan hệ
                $store = $store ?: Store::findOrFail($storeId);

                $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);

                // Category cho commission: chỉ dùng khi tất cả item cùng 1 category.
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
                    'commission_rate' => $rate, // snapshot, cố định cho đơn này
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

                // QUYẾT ĐỊNH 2026-09-19: KHÔNG giữ tiền vào ví ngay lúc đặt hàng nữa —
                // seller_order còn 'pending' (khách vừa đặt, seller chưa xác nhận) thì
                // chưa có gì đảm bảo đơn sẽ được giao. Giữ tiền (hold) chỉ xảy ra lúc
                // seller bàn giao vận chuyển (SellerOrderService::handover(), status
                // 'shipping') — cùng thời điểm với lúc kho bị trừ thật.
                $orderTotal += $subtotal;
            }

            $grandTotal = max(0, round($orderTotal + $totalShippingFee - $discountAmount, 2));
            $order->update(['total_amount' => $grandTotal]);

            return $order->load('sellerOrders.items');
        });
    }

    /**
     * Hủy đơn — CHỈ khi tất cả SellerOrder còn 'pending' (khách được hủy khi
     * đang chờ xác nhận; khi seller đã confirmed/shipping/... không cho hủy nữa,
     * dùng chung enum trạng thái với SellerOrderService::TRANSITIONS).
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $sellerOrders = $order->sellerOrders()->with('items')->lockForUpdate()->get();

            if ($sellerOrders->isEmpty() || $sellerOrders->contains(fn (SellerOrder $so) => $so->status !== 'pending')) {
                throw new RuntimeException('Đơn hàng đã được xác nhận hoặc đang giao, không thể hủy.');
            }

            // Không cần hoàn kho/ví — chỉ hủy được khi TẤT CẢ seller_orders còn
            // 'pending' (điều kiện chặn ở trên), tức chưa từng qua handover() nên
            // kho/ví chưa từng bị trừ/giữ (xem SellerOrderService::handover()).
            foreach ($sellerOrders as $sellerOrder) {
                $sellerOrder->update(['status' => 'cancelled']);
            }

            $order->update(['status' => 'cancelled']);

            return $order->refresh()->load('sellerOrders.items');
        });
    }
}
