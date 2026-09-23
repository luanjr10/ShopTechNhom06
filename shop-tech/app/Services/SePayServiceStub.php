<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stub SePay — chỉ dùng để test flow end-to-end khi CHƯA có tài khoản SePay
 * merchant thật. KHÔNG gọi my.sepay.vn. Thay vào đó:
 *
 *   1. createCheckoutUrl(): trả về URL trang mock trên Laravel local (route
 *      '/sepay-stub/checkout') — hiển thị QR giả + 2 nút "Đã thanh toán / Hủy".
 *   2. Nút "Đã thanh toán" -> gọi POST /api/payments/sepay/webhook/stub (mô
 *      phỏng SePay bắn webhook) -> Service tự đối soát và mark order paid
 *      giống webhook thật.
 *
 * Cùng public API với SePayService (createCheckoutUrl, verifyWebhookAuth,
 * findOrderFromWebhook) nên PaymentController có thể swap qua container mà
 * không sửa code controller. Bật bằng SEPAY_STUB=true trong .env.
 */
class SePayServiceStub
{
    /**
     * Trả về URL trang mock checkout. Lưu payment_ref + sepay_invoice_id
     * tương tự SePay thật để các flow verify/order-tiep-tuc không phải sửa.
     */
    public function createCheckoutUrl(Order $order): string
    {
        // payment_ref unique mỗi lần gọi (giống SePay thật có invoice riêng).
        $orderRef = 'STUBSEPAY'.$order->id.'-'.time();

        $order->update([
            'payment_ref' => $orderRef,
            'sepay_invoice_id' => 'stub_'.$order->id.'_'.now()->timestamp,
        ]);

        return url('/sepay-stub/checkout?'.http_build_query([
            'order_id' => $order->id,
            'ref' => $orderRef,
            'amount' => (int) round((float) $order->total_amount),
        ]));
    }

    /**
     * Stub webhook: KHÔNG check Bearer (SePay thật check Bearer API key).
     * Để tuân thủ signature public API của SePayService, vẫn nhận $authHeader
     * và trả true. Security: chỉ nên enable khi SEPAY_STUB=true (chỉ dev).
     */
    public function verifyWebhookAuth(?string $authHeader): bool
    {
        return true;
    }

    /**
     * Tìm Order từ payload webhook stub. Stub đơn giản: nhận 'order_id'.
     */
    public function findOrderFromWebhook(array $payload): ?Order
    {
        $orderId = (int) ($payload['order_id'] ?? 0);

        return $orderId > 0 ? Order::find($orderId) : null;
    }

    /**
     * Xử lý webhook nội bộ của stub: giống controller thật của SePay webhook
     * — mark paid nếu amount khớp. Trả về array kết quả để route handler dùng.
     *
     * @return array{ok:bool, message?:string, status?:int}
     */
    public function handleStubWebhook(array $payload): array
    {
        $order = $this->findOrderFromWebhook($payload);
        if (! $order) {
            return ['ok' => false, 'message' => 'order not found', 'status' => 200];
        }

        // Idempotent — nếu đã paid rồi thì bỏ qua (webhook có thể trùng).
        if ($order->status === 'paid' || $order->paid_at) {
            return ['ok' => true, 'message' => 'already paid'];
        }

        if ($order->status !== 'pending') {
            return ['ok' => false, 'message' => 'order not in payable state', 'status' => 200];
        }

        $transferAmount = (int) ($payload['transferAmount'] ?? 0);
        $expected = (int) round((float) $order->total_amount);

        if ($transferAmount < $expected) {
            return ['ok' => false, 'message' => 'amount mismatch', 'status' => 200];
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'sepay_transaction_id' => (string) ($payload['transactionId'] ?? 'STUBTX'.time()),
            ]);
        });

        Log::info('STUB SePay webhook marked order paid', [
            'order_id' => $order->id,
            'amount' => $transferAmount,
        ]);

        return ['ok' => true];
    }
}
