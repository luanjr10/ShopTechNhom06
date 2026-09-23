<?php

namespace App\Services;

use App\Mail\OrderInvoiceMail;
use App\Models\Order;
use App\Models\SellerOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Mail;

/**
 * Hóa đơn KHÔNG phải 1 bảng riêng — chỉ là 1 cách trình bày lại Order/SellerOrder
 * đã có, luôn phản ánh ĐÚNG trạng thái hiện tại của đơn (kể cả đã hoàn tất hay
 * đã hủy) vì dữ liệu lấy trực tiếp từ Order/SellerOrder, không lưu snapshot.
 *
 * - buildFromOrder(): hóa đơn ĐẦY ĐỦ (admin xem) — gồm mọi seller trong đơn.
 * - buildFromSellerOrder(): hóa đơn RIÊNG của 1 seller (seller chỉ xem/gửi
 *   được phần hàng của MÌNH, không thấy hàng của seller khác trong cùng đơn).
 *
 * Cả 2 trả về CÙNG 1 cấu trúc mảng để dùng chung 1 view PDF/email.
 */
class InvoiceService
{
    private const ORDER_STATUS_LABELS = [
        'pending' => 'Chờ xác nhận',
        'paid' => 'Đã thanh toán',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
    ];

    private const SELLER_ORDER_STATUS_LABELS = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao',
        'delivered' => 'Đã giao hàng',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
    ];

    public function buildFromOrder(Order $order): array
    {
        $order->loadMissing('user', 'sellerOrders.store', 'sellerOrders.items');

        $groups = $order->sellerOrders->map(fn (SellerOrder $so) => $this->group($so))->values()->all();

        return [
            'invoice_no' => 'DH-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'issued_at' => now(),
            'status' => $order->status,
            'status_label' => self::ORDER_STATUS_LABELS[$order->status] ?? $order->status,
            'customer' => [
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
            ],
            'receiver' => [
                'name' => $order->receiver_name,
                'phone' => $order->receiver_phone,
                'address' => $order->shipping_address,
            ],
            'payment_method' => $order->payment_method,
            'paid_at' => $order->paid_at,
            'created_at' => $order->created_at,
            'groups' => $groups,
            'subtotal' => (float) $order->sellerOrders->sum('subtotal'),
            'shipping_fee' => (float) $order->shipping_fee,
            'discount_amount' => (float) $order->discount_amount,
            'discount_code' => $order->discount_code,
            'total' => (float) $order->total_amount,
        ];
    }

    public function buildFromSellerOrder(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing('order.user', 'store', 'items');
        $order = $sellerOrder->order;

        return [
            'invoice_no' => 'DH-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT).'-S'.$sellerOrder->id,
            'issued_at' => now(),
            'status' => $sellerOrder->status,
            'status_label' => self::SELLER_ORDER_STATUS_LABELS[$sellerOrder->status] ?? $sellerOrder->status,
            'customer' => [
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
            ],
            'receiver' => [
                'name' => $order->receiver_name,
                'phone' => $order->receiver_phone,
                'address' => $order->shipping_address,
            ],
            'payment_method' => $order->payment_method,
            'paid_at' => $order->paid_at,
            'created_at' => $order->created_at,
            'groups' => [$this->group($sellerOrder)],
            'subtotal' => (float) $sellerOrder->subtotal,
            'shipping_fee' => (float) $sellerOrder->shipping_fee,
            'discount_amount' => 0.0,
            'discount_code' => null,
            'total' => (float) ($sellerOrder->subtotal + $sellerOrder->shipping_fee),
        ];
    }

    private function group(SellerOrder $sellerOrder): array
    {
        return [
            'store_name' => $sellerOrder->store->name ?? '—',
            'status' => $sellerOrder->status,
            'status_label' => self::SELLER_ORDER_STATUS_LABELS[$sellerOrder->status] ?? $sellerOrder->status,
            'items' => $sellerOrder->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->sku,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])->all(),
            'subtotal' => (float) $sellerOrder->subtotal,
            'shipping_fee' => (float) $sellerOrder->shipping_fee,
        ];
    }

    public function renderPdf(array $invoice): PdfDocument
    {
        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
    }

    public function email(array $invoice, string $toEmail): void
    {
        Mail::to($toEmail)->send(new OrderInvoiceMail($invoice));
    }
}
