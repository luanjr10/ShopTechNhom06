<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WithdrawalRequest;

class OnePayService
{
    public function createPaymentUrl(Order $order, string $flow = 'domestic'): string
    {
        $accessCode = config('services.onepay.access_code');
        $hashCode = config('services.onepay.hash_code');
        $merchantId = config('services.onepay.merchant_id');

        $merchTxnRef = 'ORDER'.$order->id.'-'.time();
        $amount = (int) round((float) $order->total_amount) * 100;

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

        if ($againLink = config('services.onepay.again_link')) {
            $params['vpc_AgainLink'] = $againLink;
        }

        $params['vpc_SecureHash'] = $this->sign($params, $hashCode);

        $baseUrl = $flow === 'international'
            ? config('services.onepay.international_url')
            : config('services.onepay.domestic_url');

        $order->update(['payment_ref' => $merchTxnRef]);

        return $this->buildPaymentUrl($baseUrl, $params);
    }

    public function createWithdrawalPaymentUrl(WithdrawalRequest $withdrawal, string $flow = 'domestic'): string
    {
        $accessCode = config('services.onepay.access_code');
        $hashCode = config('services.onepay.hash_code');
        $merchantId = config('services.onepay.merchant_id');

        $merchTxnRef = 'WD'.$withdrawal->id.'-'.time();
        $amount = (int) round((float) $withdrawal->amount) * 100;

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

    public function verifySignature(array $params): bool
    {
        $secureHash = strtoupper((string) ($params['vpc_SecureHash'] ?? ''));
        unset($params['vpc_SecureHash']);

        $expected = $this->sign($params, config('services.onepay.hash_code'));

        return hash_equals($expected, $secureHash);
    }

    public function isSuccess(array $params): bool
    {
        return (string) ($params['vpc_TxnResponseCode'] ?? '') === '0';
    }

    private function buildPaymentUrl(string $baseUrl, array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key.'='.rawurlencode((string) $value);
        }

        return $baseUrl.'?'.implode('&', $parts);
    }

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
