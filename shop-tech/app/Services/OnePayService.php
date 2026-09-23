<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WithdrawalRequest;

/**
 * Cổng thanh toán OnePay — môi trường MTF/Test (mtf.onepay.vn).
 * Hỗ trợ 2 luồng:
 *   - Domestic: thẻ ATM nội địa Napas, endpoint /onecomm-pay/vpc.op
 *   - International: Visa / MasterCard / JCB, endpoint /vpcpay/vpcpay.op
 *
 * Toàn bộ thông tin merchant (Merchant ID / Access Code / Hash Code) được
 * đọc từ config('services.onepay.*') — KHÔNG hard-code trong service.
 *
 * SecureHash theo tài liệu OnePay MTF (tài liệu tiếng Anh):
 *   1. Sắp xếp tham số vpc_* theo alphabet (ksort).
 *   2. Nối bằng '&' theo định dạng `key=value` với giá trị giữ NGUYÊN (raw,
 *      CHƯA url-encode — kể cả vpc_ReturnURL, phải ký với "http://..." raw
 *      vì phía OnePay decode URL về raw rồi mới tính lại hash để so khớp).
 *   3. Ký bằng HMAC-SHA256 với hash_code lấy từ config.
 *   4. SecureHash uppercase hex.
 *   5. Khi build URL redirect, encode TỪNG giá trị đúng 1 lần (rawurlencode).
 *
 * Lưu ý về số tiền: `vpc_Amount` x100, không có phần lẻ.
 */
class OnePayService
{
    /**
     * Tạo URL redirect sang OnePay MTF (domestic/international), ký SecureHash
     * và lưu payment_ref duy nhất (MerchTxnRef) vào Order.
     */
    public function createPaymentUrl(Order $order, string $flow = 'domestic'): string
    {
        $accessCode = config('services.onepay.access_code');
        $hashCode = config('services.onepay.hash_code');
        $merchantId = config('services.onepay.merchant_id');

        // MerchTxnRef phải duy nhất mỗi lần tạo (kể cả retry trên cùng 1 order).
        $merchTxnRef = 'ORDER'.$order->id.'-'.time();
        $amount = (int) round((float) $order->total_amount) * 100; // x100, không phần lẻ

        // Field bắt buộc theo tài liệu OnePay MTF. Chỉ giữ các field cốt lõi
        // — sandbox từ chối nếu thêm field không có trong schema.
        $returnUrlRaw = config('services.onepay.return_url') ?: route('payments.onepay.return');

        $params = [
            'vpc_Version' => '2',
            'vpc_Command' => 'pay',
            'vpc_MerchTxnRef' => $merchTxnRef,
            'vpc_Merchant' => $merchantId,
            'vpc_AccessCode' => $accessCode,
            'vpc_Amount' => $amount,
            'vpc_Currency' => 'VND',
            'vpc_Locale' => 'vn',
            'vpc_ReturnURL' => $returnUrlRaw,
            'vpc_OrderInfo' => "Thanh toan don hang ShopTech #{$order->id}",
            'vpc_TicketNo' => request()->ip() ?: '127.0.0.1',
        ];

        // AgainLink: OnePay thêm nút "Thử lại" sau kết quả thất bại.
        if ($againLink = config('services.onepay.again_link')) {
            $params['vpc_AgainLink'] = $againLink;
        }

        $params['vpc_SecureHash'] = $this->sign($params, $hashCode);

        // Domestic/International chọn bằng URL (không qua field) — OnePay MTF dùng
        // 2 endpoint riêng, không có field flow.
        $baseUrl = $flow === 'international'
            ? config('services.onepay.international_url')
            : config('services.onepay.domestic_url');

        $order->update(['payment_ref' => $merchTxnRef]);

        return $this->buildPaymentUrl($baseUrl, $params);
    }

    /**
     * Cùng luồng createPaymentUrl() nhưng cho ADMIN duyệt rút tiền — xem
     * doc-block MomoService::createWithdrawalPaymentUrl().
     */
    public function createWithdrawalPaymentUrl(WithdrawalRequest $withdrawal, string $flow = 'domestic'): string
    {
        $accessCode = config('services.onepay.access_code');
        $hashCode = config('services.onepay.hash_code');
        $merchantId = config('services.onepay.merchant_id');

        $merchTxnRef = 'WD'.$withdrawal->id.'-'.time();
        $amount = (int) round((float) $withdrawal->amount) * 100;

        // KHÔNG dùng config('services.onepay.return_url') ở đây — biến đó cấu
        // hình CỨNG cho luồng đơn hàng (ONEPAY_RETURN_URL trỏ .../payments/
        // onepay/return), dùng nhầm sẽ khiến OnePay trả kết quả về nhầm handler.
        $returnUrlRaw = route('payments.onepay.withdrawal-return');

        $params = [
            'vpc_Version' => '2',
            'vpc_Command' => 'pay',
            'vpc_MerchTxnRef' => $merchTxnRef,
            'vpc_Merchant' => $merchantId,
            'vpc_AccessCode' => $accessCode,
            'vpc_Amount' => $amount,
            'vpc_Currency' => 'VND',
            'vpc_Locale' => 'vn',
            'vpc_ReturnURL' => $returnUrlRaw,
            'vpc_OrderInfo' => "Giai ngan rut tien ShopTech #{$withdrawal->id}",
            'vpc_TicketNo' => request()->ip() ?: '127.0.0.1',
        ];

        if ($againLink = config('services.onepay.again_link')) {
            $params['vpc_AgainLink'] = $againLink;
        }

        $params['vpc_SecureHash'] = $this->sign($params, $hashCode);

        $baseUrl = $flow === 'international'
            ? config('services.onepay.international_url')
            : config('services.onepay.domestic_url');

        $withdrawal->update(['payout_reference' => $merchTxnRef]);

        return $this->buildPaymentUrl($baseUrl, $params);
    }

    /**
     * @param  array<string, string>  $params  Toàn bộ query OnePay trả về.
     */
    public function verifySignature(array $params): bool
    {
        $secureHash = strtoupper((string) ($params['vpc_SecureHash'] ?? ''));
        unset($params['vpc_SecureHash']);

        // Khi OnePay gọi return về, nó decode URL trước khi gửi cho ta — giá trị
        // đã được decode về dạng raw. Vì vậy cần truyền raw value cho hàm sign().
        $expected = $this->sign($params, config('services.onepay.hash_code'));

        return hash_equals($expected, $secureHash);
    }

    /**
     * OnePay trả về `vpc_TxnResponseCode` (3 chữ số): '0' = thành công.
     */
    public function isSuccess(array $params): bool
    {
        return (string) ($params['vpc_TxnResponseCode'] ?? '') === '0';
    }

    /**
     * Build URL cuối cùng để redirect sang OnePay: ghép query string, encode
     * từng giá trị đúng 1 lần theo RFC 3986 (rawurlencode).
     */
    private function buildPaymentUrl(string $baseUrl, array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key.'='.rawurlencode((string) $value);
        }

        return $baseUrl.'?'.implode('&', $parts);
    }

    /**
     * Ký SecureHash: ksort + nối key=value bằng '&' trên giá trị RAW, HMAC-SHA256
     * -> uppercase hex. CHUNG công thức cho cả create và verify.
     *
     * Giao thức OnePay VPC kế thừa từ MIGS: Hash Code cấu hình là chuỗi HEX
     * của secret key, PHẢI decode về binary (hex2bin) trước khi dùng làm key
     * HMAC — dùng thẳng chuỗi hex làm key (ASCII) sẽ luôn ra hash sai.
     */
    private function sign(array $params, string $hashCode): string
    {
        ksort($params);

        $signData = '';
        foreach ($params as $key => $value) {
            $signData .= ($signData === '' ? '' : '&').$key.'='.$value;
        }

        return strtoupper(hash_hmac('SHA256', $signData, hex2bin($hashCode)));
    }
}
