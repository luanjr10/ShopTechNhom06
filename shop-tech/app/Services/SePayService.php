<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WithdrawalRequest;

/**
 * SePay Payment Gateway (my.sepay.vn/pg) — quét mã QR chuyển khoản ngân hàng.
 *
 * KHÁC với MoMo/VNPay/OnePay: đây KHÔNG phải REST API trả JSON, mà là endpoint
 * nhận SUBMIT FORM HTML (POST) từ trình duyệt khách hàng — server chỉ chuẩn bị
 * tham số + chữ ký, việc "gọi API" thực chất là để trình duyệt tự POST form.
 * Tài liệu: https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/form-thanh-toan
 *
 * Luồng hoạt động:
 *   1. buildCheckoutForm(): tạo order_invoice_number (lưu vào payment_ref) +
 *      ký HMAC-SHA256 -> trả {action, fields} để FE render form rồi auto-submit.
 *   2. Khách chọn phương thức trên trang SePay (QR/thẻ) → thanh toán.
 *   3. success_url/error_url/cancel_url: SePay redirect trình duyệt về đây SAU
 *      KHI thanh toán — nhưng các URL này KHÔNG được ký, ai cũng tự gọi được
 *      (chỉ là link tĩnh do mình định nghĩa) => TUYỆT ĐỐI không dùng để mark
 *      paid, chỉ để hiển thị UX "đang chờ xác nhận".
 *   4. IPN (server-to-server, có ký X-Secret-Key): nguồn DUY NHẤT đáng tin để
 *      xác nhận thanh toán — xem verifyIpnAuth()/findOrderFromIpn()/isOrderPaid().
 *
 * IPN chỉ hoạt động khi merchant cấu hình được IPN URL công khai (SePay từ
 * chối lưu URL localhost) — trên local cần ngrok/cloudflared, giống bẫy IPN
 * MoMo/VNPay đã gặp trước đó.
 */
class SePayService
{
    /**
     * Field được phép ký, ĐÚNG THỨ TỰ theo tài liệu SePay — đổi thứ tự sẽ làm
     * signature sai dù giá trị giống hệt.
     */
    private const SIGNED_FIELDS = [
        'order_amount', 'merchant', 'currency', 'operation',
        'order_description', 'order_invoice_number', 'customer_id',
        'payment_method', 'success_url', 'error_url', 'cancel_url',
    ];

    /**
     * Chuẩn bị tham số form thanh toán SePay cho 1 Order. Idempotent: nếu Order
     * đã có payment_ref (gọi lại — ví dụ khách bấm "Thanh toán lại") thì dùng
     * lại đúng order_invoice_number cũ thay vì tạo mã mới, để URL redirect ổn định.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function buildCheckoutForm(Order $order): array
    {
        $orderRef = $order->payment_ref ?: 'SEPAY_ORDER'.$order->id.'_'.time();
        if (! $order->payment_ref) {
            $order->update(['payment_ref' => $orderRef]);
        }

        $fields = [
            'order_amount' => (string) (int) round((float) $order->total_amount),
            'merchant' => (string) config('services.sepay.merchant_id'),
            'currency' => 'VND',
            'operation' => 'PURCHASE',
            'order_description' => "Thanh toan don hang ShopTech #{$order->id}",
            'order_invoice_number' => $orderRef,
            'success_url' => route('payments.sepay.return', ['order' => $order->id, 'status' => 'success']),
            'error_url' => route('payments.sepay.return', ['order' => $order->id, 'status' => 'error']),
            'cancel_url' => route('payments.sepay.return', ['order' => $order->id, 'status' => 'cancel']),
        ];

        $fields['signature'] = $this->sign($fields, (string) config('services.sepay.secret_key'));

        return [
            'action' => config('services.sepay.checkout_url'),
            'fields' => $fields,
        ];
    }

    /**
     * Cùng luồng buildCheckoutForm() nhưng cho ADMIN duyệt rút tiền — xem
     * doc-block MomoService::createWithdrawalPaymentUrl(). CHÚ Ý: return_url
     * của SePay KHÔNG được ký (đã ghi rõ ở doc-block class), nên
     * WithdrawalPaymentController::sepayWithdrawalReturn() chấp nhận đánh dấu
     * đã giải ngân ngay tại return thay vì chờ IPN — chấp nhận được vì đây là
     * hành động ADMIN tự khởi tạo và tự hoàn tất (không phải bên thứ 3 không
     * đáng tin như khách hàng), và bản thân giao dịch cũng chỉ là MÔ PHỎNG.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function buildWithdrawalCheckoutForm(WithdrawalRequest $withdrawal): array
    {
        $orderRef = 'WD'.$withdrawal->id.'-'.time();

        $fields = [
            'order_amount' => (string) (int) round((float) $withdrawal->amount),
            'merchant' => (string) config('services.sepay.merchant_id'),
            'currency' => 'VND',
            'operation' => 'PURCHASE',
            'order_description' => "Giai ngan rut tien ShopTech #{$withdrawal->id}",
            'order_invoice_number' => $orderRef,
            'success_url' => route('payments.sepay.withdrawal-return', ['withdrawal' => $withdrawal->id, 'status' => 'success']),
            'error_url' => route('payments.sepay.withdrawal-return', ['withdrawal' => $withdrawal->id, 'status' => 'error']),
            'cancel_url' => route('payments.sepay.withdrawal-return', ['withdrawal' => $withdrawal->id, 'status' => 'cancel']),
        ];

        $fields['signature'] = $this->sign($fields, (string) config('services.sepay.secret_key'));

        $withdrawal->update(['payout_reference' => $orderRef]);

        return [
            'action' => config('services.sepay.checkout_url'),
            'fields' => $fields,
        ];
    }

    /**
     * Verify header `X-Secret-Key` của IPN — merchant cấu hình Auth Type =
     * "Secret Key" trên dashboard SePay thì mới có header này.
     */
    public function verifyIpnAuth(?string $secretKeyHeader): bool
    {
        $expected = config('services.sepay.ipn_secret');
        if (! $expected || ! $secretKeyHeader) {
            return false;
        }

        return hash_equals((string) $expected, $secretKeyHeader);
    }

    /**
     * Tìm Order tương ứng payload IPN — khớp theo order.order_invoice_number
     * (chính là payment_ref đã lưu lúc buildCheckoutForm()).
     */
    public function findOrderFromIpn(array $payload): ?Order
    {
        $invoiceNumber = $payload['order']['order_invoice_number'] ?? null;
        if (! $invoiceNumber) {
            return null;
        }

        return Order::where('payment_ref', $invoiceNumber)->first();
    }

    /**
     * IPN báo thanh toán thành công khi notification_type = ORDER_PAID.
     */
    public function isOrderPaid(array $payload): bool
    {
        return ($payload['notification_type'] ?? null) === 'ORDER_PAID';
    }

    /**
     * Số tiền IPN báo về (order.order_amount, dạng string "100000.00") — dùng
     * đối soát tránh trường hợp giao dịch giả/khớp nhầm order.
     */
    public function ipnAmount(array $payload): float
    {
        return (float) ($payload['order']['order_amount'] ?? 0);
    }

    public function ipnTransactionId(array $payload): ?string
    {
        $id = $payload['transaction']['transaction_id'] ?? null;

        return $id !== null ? (string) $id : null;
    }

    /**
     * Ký form: lọc field theo SIGNED_FIELDS (giữ đúng thứ tự khai báo), nối
     * "field=value" bằng dấu phẩy, HMAC-SHA256 rồi base64 (KHÔNG uppercase hex
     * như OnePay/VNPay — SePay dùng base64 của binary HMAC).
     */
    private function sign(array $fields, string $secretKey): string
    {
        $signed = [];
        foreach (self::SIGNED_FIELDS as $field) {
            if (! isset($fields[$field])) {
                continue;
            }
            $signed[] = $field.'='.$fields[$field];
        }

        return base64_encode(hash_hmac('sha256', implode(',', $signed), $secretKey, true));
    }
}
