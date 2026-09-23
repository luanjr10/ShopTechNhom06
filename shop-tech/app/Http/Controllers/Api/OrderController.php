<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Rules\VietnamesePhone;
use App\Services\OrderService;
use App\Services\SellerOrderService;
use App\Services\WarrantyService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Customer đặt hàng (có thể mua từ nhiều Store trong 1 lần checkout) và xem đơn của mình.
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private SellerOrderService $sellerOrderService,
        private WarrantyService $warrantyService,
    ) {}

    // [POST] /api/orders
    public function place(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.sku' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'receiver_name' => 'required|string|max:150',
            'receiver_phone' => ['required', 'string', new VietnamesePhone],
            'shipping_address' => 'required|string|max:255',
            // Mã GHN (hệ cũ, có quận/huyện) — bắt buộc để tính phí/tạo vận đơn thật.
            'province_id' => 'required|integer',
            'province_name' => 'required|string|max:150',
            'district_id' => 'required|integer',
            'district_name' => 'required|string|max:150',
            'ward_code' => 'required|string|max:20',
            'ward_name' => 'required|string|max:150',
            'payment_method' => 'required|in:cod,momo,vnpay,onepay,sepay',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        try {
            $order = $this->orderService->place(
                $request->user(),
                $validated['items'],
                [
                    'receiver_name' => $validated['receiver_name'],
                    'receiver_phone' => $validated['receiver_phone'],
                    'shipping_address' => $validated['shipping_address'],
                    'province_id' => $validated['province_id'],
                    'province_name' => $validated['province_name'],
                    'district_id' => $validated['district_id'],
                    'district_name' => $validated['district_name'],
                    'ward_code' => $validated['ward_code'],
                    'ward_name' => $validated['ward_name'],
                    'payment_method' => $validated['payment_method'],
                    'coupon_code' => $validated['coupon_code'] ?? null,
                ],
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Đặt hàng thất bại', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đặt hàng thành công',
            'data' => $order,
        ], 201);
    }

    // [GET] /api/orders/mine
    public function mine(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('sellerOrders.store:id,name,slug', 'sellerOrders.items', 'sellerOrders.shipment')
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $orders], 200);
    }

    // [GET] /api/orders/{order}
    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->withWarrantyAndReturns($order),
        ], 200);
    }

    /**
     * Gắn thông tin bảo hành (theo seller_order) + yêu cầu hoàn trả gần nhất
     * (theo order_item) vào response — để FE hiện badge "còn hạn/hết hạn" và
     * trạng thái yêu cầu mà không phải gọi thêm API riêng.
     */
    private function withWarrantyAndReturns(Order $order): Order
    {
        $order->load('sellerOrders.store:id,name,slug', 'sellerOrders.items', 'sellerOrders.shipment');

        $itemIds = $order->sellerOrders->flatMap(fn (SellerOrder $so) => $so->items->pluck('id'));
        $latestReturnsByItem = ReturnRequest::whereIn('order_item_id', $itemIds)
            ->latest()
            ->get()
            ->unique('order_item_id')
            ->keyBy('order_item_id');

        foreach ($order->sellerOrders as $sellerOrder) {
            $sellerOrder->setAttribute('warranty', $this->warrantyService->statusForSellerOrder($sellerOrder, $order));
            $sellerOrder->setAttribute('can_request_return', $this->warrantyService->canRequestReturn($sellerOrder));

            foreach ($sellerOrder->items as $item) {
                $item->setAttribute('return_request', $latestReturnsByItem->get($item->id));
            }
        }

        return $order;
    }

    // [POST] /api/orders/{order}/cancel — chỉ khi TẤT CẢ seller order còn 'pending' (chưa được xác nhận/đang giao).
    public function cancel(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        try {
            $order = $this->orderService->cancel($order);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy đơn hàng',
            'data' => $order->load('sellerOrders.store:id,name,slug', 'sellerOrders.items', 'sellerOrders.shipment'),
        ], 200);
    }

    // [POST] /api/orders/{order}/seller-orders/{sellerOrder}/complete — customer
    // xác nhận ĐÃ NHẬN hàng. CHỈ hợp lệ khi seller_order đang 'delivered' (do
    // webhook GHN set) — không cho nhảy thẳng từ shipping/confirmed.
    public function completeSellerOrder(Request $request, Order $order, SellerOrder $sellerOrder)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }
        if ($sellerOrder->order_id !== $order->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc order này'], 404);
        }

        try {
            $updated = $this->sellerOrderService->complete($sellerOrder);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận nhận hàng',
            'data' => $updated,
        ], 200);
    }
}
