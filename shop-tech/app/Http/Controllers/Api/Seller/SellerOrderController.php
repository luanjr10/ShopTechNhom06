<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\InvoiceService;
use App\Services\SellerOrderService;
use Illuminate\Http\Request;
use RuntimeException;

class SellerOrderController extends Controller
{
    public function __construct(
        private SellerOrderService $sellerOrderService,
        private InvoiceService $invoiceService,
    ) {}

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

    public function invoicePdf(Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        $invoice = $this->invoiceService->buildFromSellerOrder($sellerOrder);

        return $this->invoiceService->renderPdf($invoice)->stream("{$invoice['invoice_no']}.pdf");
    }

    public function emailInvoice(Request $request, Store $store, SellerOrder $sellerOrder)
    {
        if ($sellerOrder->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Đơn không thuộc gian hàng này'], 404);
        }

        $validated = $request->validate(['email' => 'nullable|email:rfc,dns']);

        $invoice = $this->invoiceService->buildFromSellerOrder($sellerOrder);
        $this->invoiceService->email($invoice, $validated['email'] ?? $sellerOrder->order->user->email);

        return response()->json(['success' => true, 'message' => 'Đã gửi hóa đơn qua email'], 200);
    }
}
