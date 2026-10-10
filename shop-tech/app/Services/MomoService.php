<?php

namespace App\Services;

use App\Models\AffiliateWithdrawal;
use App\Models\InstallmentPayment;
use App\Models\Order;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MomoService
{
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

    public function createInstallmentPaymentUrl(InstallmentPayment $payment): string
    {
        $partnerCode = config('services.momo.partner_code');
        $accessKey = config('services.momo.access_key');
        $secretKey = config('services.momo.secret_key');

        $momoOrderId = $partnerCode.'-IP'.$payment->id.'-'.time();
        $requestId = $momoOrderId;
        $amount = (string) (int) round((float) $payment->amount);
        $orderInfo = "Tra gop ShopTech don #{$payment->plan->order_id} ky {$payment->number}/{$payment->plan->months}";
        $extraData = '';
        $redirectUrl = route('payments.momo.installment-return');
        $ipnUrl = $redirectUrl;

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

        $payment->update(['payment_ref' => $momoOrderId]);

        return $result['payUrl'];
    }

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
        $ipnUrl = route('payments.momo.withdrawal-return');

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
     * Gian hàng chi trả hoa hồng affiliate cho người giới thiệu qua cổng MoMo sandbox.
     */
    public function createAffiliatePayoutUrl(AffiliateWithdrawal $withdrawal): string
    {
        $partnerCode = config('services.momo.partner_code');
        $accessKey = config('services.momo.access_key');
        $secretKey = config('services.momo.secret_key');

        $momoOrderId = $partnerCode.'-AW'.$withdrawal->id.'-'.time();
        $requestId = $momoOrderId;
        $amount = (string) (int) round((float) $withdrawal->amount);
        $orderInfo = "Chi tra hoa hong affiliate ShopTech #{$withdrawal->id}";
        $extraData = '';
        $redirectUrl = route('payments.momo.affiliate-return');
        $ipnUrl = $redirectUrl;

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
            'signature' => hash_hmac('sha256', $rawHash, $secretKey),
        ]);

        $result = $response->json();

        if (! $response->successful() || empty($result['payUrl']) || ($result['resultCode'] ?? 1) !== 0) {
            throw new RuntimeException($result['message'] ?? 'Không tạo được yêu cầu chi trả MoMo');
        }

        $withdrawal->update(['payout_reference' => $momoOrderId]);

        return $result['payUrl'];
    }

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
