<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cổng thanh toán MoMo (sandbox test-payment.momo.vn, API v2 payWithMethod).
 * Chữ ký (signature) dùng CHUNG 1 công thức cho cả tạo giao dịch, return và IPN
 * theo đúng tài liệu MoMo — KHÔNG tự chế công thức khác nhau cho từng bước.
 */
class MomoService
{
    /**
     * Tạo yêu cầu thanh toán, lưu payment_ref vào order và trả về payUrl để FE
     * redirect. orderId gửi cho MoMo PHẢI duy nhất mỗi lần gọi (khác order.id
     * nội bộ vì có thể tạo lại yêu cầu cho cùng 1 order khi thử lại thanh toán).
     */
    public function createPaymentUrl(Order $order): string
    {
        $partnerCode = config('services.momo.partner_code');
        $accessKey = config('services.momo.access_key');
        $secretKey = config('services.momo.secret_key');

        $momoOrderId = $partnerCode.'-'.$order->id.'-'.time();
        $requestId = $momoOrderId;
        $amount = (string) (int) round((float) $order->total_amount);
        $orderInfo = "Thanh toan don hang ShopTech #{$order->id}";
        $extraData = '';
        $redirectUrl = route('payments.momo.return');
        $ipnUrl = route('payments.momo.notify');

        $rawHash = "accessKey={$accessKey}"
            ."&amount={$amount}"
            ."&extraData={$extraData}"
            ."&ipnUrl={$ipnUrl}"
            ."&orderId={$momoOrderId}"
            ."&orderInfo={$orderInfo}"
            ."&partnerCode={$partnerCode}"
            ."&redirectUrl={$redirectUrl}"
            ."&requestId={$requestId}"
            .'&requestType=payWithMethod';

        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $response = Http::timeout(20)->post(config('services.momo.endpoint'), [
            'partnerCode' => $partnerCode,
            'partnerName' => 'ShopTech',
            'storeId' => 'ShopTechStore',
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $momoOrderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'autoCapture' => true,
            'extraData' => $extraData,
            'requestType' => 'payWithMethod',
            'signature' => $signature,
        ]);

        $result = $response->json();

        if (! $response->successful() || empty($result['payUrl']) || ($result['resultCode'] ?? 1) !== 0) {
            throw new RuntimeException($result['message'] ?? 'Không tạo được yêu cầu thanh toán MoMo');
        }

        $order->update(['payment_ref' => $momoOrderId]);

        return $result['payUrl'];
    }

    /**
     * Cùng luồng như createPaymentUrl() nhưng cho ADMIN duyệt rút tiền (không
     * có Order) — admin bấm "Duyệt" -> sang thẳng trang MoMo sandbox thật,
     * nhập ví/OTP test giống hệt khách hàng checkout, xem WithdrawalPaymentController.
     * Lưu tham chiếu vào `withdrawal.payout_reference` thay vì order.payment_ref.
     */
    public function createWithdrawalPaymentUrl(WithdrawalRequest $withdrawal): string
    {
        $partnerCode = config('services.momo.partner_code');
        $accessKey = config('services.momo.access_key');
        $secretKey = config('services.momo.secret_key');

        $momoOrderId = $partnerCode.'-WD'.$withdrawal->id.'-'.time();
        $requestId = $momoOrderId;
        $amount = (string) (int) round((float) $withdrawal->amount);
        $orderInfo = "Giai ngan rut tien ShopTech #{$withdrawal->id}";
        $extraData = '';
        $redirectUrl = route('payments.momo.withdrawal-return');
        $ipnUrl = route('payments.momo.withdrawal-return'); // Không có IPN riêng cho luồng admin — xác nhận ngay ở return (xem doc-block controller).

        $rawHash = "accessKey={$accessKey}"
            ."&amount={$amount}"
            ."&extraData={$extraData}"
            ."&ipnUrl={$ipnUrl}"
            ."&orderId={$momoOrderId}"
            ."&orderInfo={$orderInfo}"
            ."&partnerCode={$partnerCode}"
            ."&redirectUrl={$redirectUrl}"
            ."&requestId={$requestId}"
            .'&requestType=payWithMethod';

        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $response = Http::timeout(20)->post(config('services.momo.endpoint'), [
            'partnerCode' => $partnerCode,
            'partnerName' => 'ShopTech',
            'storeId' => 'ShopTechStore',
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $momoOrderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'autoCapture' => true,
            'extraData' => $extraData,
            'requestType' => 'payWithMethod',
            'signature' => $signature,
        ]);

        $result = $response->json();

        if (! $response->successful() || empty($result['payUrl']) || ($result['resultCode'] ?? 1) !== 0) {
            throw new RuntimeException($result['message'] ?? 'Không tạo được yêu cầu thanh toán MoMo');
        }

        $withdrawal->update(['payout_reference' => $momoOrderId]);

        return $result['payUrl'];
    }

    /**
     * Đúng công thức MoMo dùng cho CẢ return (redirect trình duyệt) VÀ IPN
     * (server-to-server) — accessKey/amount/extraData/message/orderId/orderInfo/
     * orderType/partnerCode/payType/requestId/responseTime/resultCode/transId.
     */
    public function verifySignature(array $data): bool
    {
        $accessKey = config('services.momo.access_key');
        $secretKey = config('services.momo.secret_key');
        $partnerCode = config('services.momo.partner_code');

        $rawHash = "accessKey={$accessKey}"
            .'&amount='.($data['amount'] ?? '')
            .'&extraData='.($data['extraData'] ?? '')
            .'&message='.($data['message'] ?? '')
            .'&orderId='.($data['orderId'] ?? '')
            .'&orderInfo='.($data['orderInfo'] ?? '')
            .'&orderType='.($data['orderType'] ?? '')
            ."&partnerCode={$partnerCode}"
            .'&payType='.($data['payType'] ?? '')
            .'&requestId='.($data['requestId'] ?? '')
            .'&responseTime='.($data['responseTime'] ?? '')
            .'&resultCode='.($data['resultCode'] ?? '')
            .'&transId='.($data['transId'] ?? '');

        $expected = hash_hmac('sha256', $rawHash, $secretKey);

        return hash_equals($expected, (string) ($data['signature'] ?? ''));
    }
}
