<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

/**
 * Admin xem toàn bộ đơn hàng của hệ thống.
 */
class OrderController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    // [GET] /api/admin/orders
    public function index(Request $request)
    {
        $query = Order::with(['user:id,name,username', 'sellerOrders.store:id,name'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [GET] /api/admin/orders/{order}
    public function show(Order $order)
    {
        return response()->json([
            'success' => true,
            'data' => $order->load('user:id,name,username,email', 'sellerOrders.store:id,name', 'sellerOrders.items'),
        ], 200);
    }

    // [GET] /api/admin/orders/{order}/invoice/pdf — xem/tải hóa đơn ĐẦY ĐỦ (mọi seller trong đơn).
    public function invoicePdf(Order $order)
    {
        $invoice = $this->invoiceService->buildFromOrder($order);

        return $this->invoiceService->renderPdf($invoice)->stream("{$invoice['invoice_no']}.pdf");
    }

    // [POST] /api/admin/orders/{order}/invoice/email — gửi hóa đơn PDF về email khách hàng.
    public function emailInvoice(Request $request, Order $order)
    {
        $validated = $request->validate(['email' => 'nullable|email:rfc,dns']);

        $invoice = $this->invoiceService->buildFromOrder($order);
        $this->invoiceService->email($invoice, $validated['email'] ?? $order->user->email);

        return response()->json(['success' => true, 'message' => 'Đã gửi hóa đơn qua email'], 200);
    }
}
