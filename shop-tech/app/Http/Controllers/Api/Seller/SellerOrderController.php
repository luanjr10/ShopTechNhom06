<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\InvoiceService;
use App\Services\SellerOrderService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Seller quản lý đơn (SellerOrder) trong một gian hàng. Mỗi SellerOrder có trạng thái riêng.
 */
class SellerOrderController extends Controller
{
    public function __construct(
        private SellerOrderService $sellerOrderService,
        private InvoiceService $invoiceService,
    ) {}

    // [GET] /api/seller/stores/{store}/orders
    public function index(Request $request, Store $store)
    {
        $query = SellerOrder::where('store_id', $store->id)
            ->with(['items', 'order:id,receiver_name,receiver_phone,shipping_address,status', 'shipment'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [GET] /api/seller/stores/{store}/orders/{sellerOrder}
    public function show(Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sellerOrder->load('items', 'order', 'shipment'),
        ], 200);
    }

    // [PATCH] /api/seller/stores/{store}/orders/{sellerOrder}/status
    // CHỈ cho xác nhận (confirmed) hoặc hủy (cancelled) — 'shipping' phải qua
    // handover(), 'delivered'/'completed' seller tự báo qua driverMarkDelivered/
    // driverMarkCancelled (xem SellerOrderService), 'completed' từ 'delivered'
    // chỉ customer tự xác nhận (xem OrderController::completeSellerOrder).
    public function updateStatus(Request $request, Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled',
        ]);

        try {
            $updated = $this->sellerOrderService->updateStatus($sellerOrder, $validated['status']);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái đơn thành công',
            'data' => $updated,
        ], 200);
    }

    // [POST] /api/seller/stores/{store}/orders/{sellerOrder}/handover — seller bàn
    // giao vận chuyển: ghi nhận Shipment nội bộ (seller tự giao, không gọi GHN thật).
    public function handover(Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        try {
            $updated = $this->sellerOrderService->handover($sellerOrder);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã bàn giao vận chuyển — vui lòng tự giao hàng cho khách.',
            'data' => $updated,
        ], 200);
    }

    // [POST] /api/seller/stores/{store}/orders/{sellerOrder}/driver-mark-delivered
    // Seller (tự làm shipper) bấm "Đã giao" — luôn chuyển shipping→delivered
    // (KHÔNG phân biệt COD/online), tiền vẫn giữ ở "đang chờ" tới khi khách tự
    // xác nhận đã nhận hàng (xem SellerOrderService::markDeliveredByDriver).
    public function driverMarkDelivered(Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        try {
            $updated = $this->sellerOrderService->markDeliveredByDriver($sellerOrder);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã đánh dấu giao hàng thành công — chờ khách xác nhận để nhận tiền.',
            'data' => $updated,
        ], 200);
    }

    // [POST] /api/seller/stores/{store}/orders/{sellerOrder}/driver-mark-cancelled
    // Seller (tự làm shipper) báo giao thất bại/huỷ — cho phép kể cả khi đã bàn giao (hoàn kho + ví).
    public function driverMarkCancelled(Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        try {
            $updated = $this->sellerOrderService->markCancelledByDriver($sellerOrder);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy đơn.',
            'data' => $updated,
        ], 200);
    }

    // [GET] /api/seller/stores/{store}/orders/{sellerOrder}/invoice/pdf — hóa đơn RIÊNG của gian hàng này.
    public function invoicePdf(Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        $invoice = $this->invoiceService->buildFromSellerOrder($sellerOrder);

        return $this->invoiceService->renderPdf($invoice)->stream("{$invoice['invoice_no']}.pdf");
    }

    // [POST] /api/seller/stores/{store}/orders/{sellerOrder}/invoice/email
    public function emailInvoice(Request $request, Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        $validated = $request->validate(['email' => 'nullable|email']);

        $invoice = $this->invoiceService->buildFromSellerOrder($sellerOrder);
        $this->invoiceService->email($invoice, $validated['email'] ?? $sellerOrder->order->user->email);

        return response()->json(['success' => true, 'message' => 'Đã gửi hóa đơn qua email'], 200);
    }
}
