<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;


class VnpayService
{

    private const VNPAY_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function createPaymentUrl(Order $order): string
    {
        $tmnCode = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');

        $txnRef = 'ORDER'.$order->id.'-'.time();
        $amount = (int) round((float) $order->total_amount) * 100;

        $nowVn = CarbonImmutable::now(self::VNPAY_TIMEZONE);

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $amount,
            'vnp_CreateDate' => $nowVn->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_ExpireDate' => $nowVn->addMinutes(15)->format('YmdHis'),
            'vnp_IpAddr' => request()->ip() ?: '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => "Thanh toan don hang ShopTech #{$order->id}",
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => route('payments.vnpay.return'),
            'vnp_TxnRef' => $txnRef,
        ];

        ksort($inputData);

        $hashData = '';
        $query = '';
        foreach ($inputData as $key => $value) {
            $encodedKey = urlencode((string) $key);
            $encodedValue = urlencode((string) $value);
            $hashData .= ($hashData === '' ? '' : '&')."{$encodedKey}={$encodedValue}";
            $query .= "{$encodedKey}={$encodedValue}&";
        }
        $query = rtrim($query, '&');

        $secureHash = hash_hmac('sha512', $hashData, $hashSecret);

        $order->update(['payment_ref' => $txnRef]);

        return config('services.vnpay.url').'?'.$query.'&vnp_SecureHash='.$secureHash;
    }


    public function createWithdrawalPaymentUrl(WithdrawalRequest $withdrawal): string
    {
        $tmnCode = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');

        $txnRef = 'WD'.$withdrawal->id.'-'.time();
        $amount = (int) round((float) $withdrawal->amount) * 100;

        $nowVn = CarbonImmutable::now(self::VNPAY_TIMEZONE);

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $amount,
            'vnp_CreateDate' => $nowVn->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_ExpireDate' => $nowVn->addMinutes(15)->format('YmdHis'),
            'vnp_IpAddr' => request()->ip() ?: '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => "Giai ngan rut tien ShopTech #{$withdrawal->id}",
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => route('payments.vnpay.withdrawal-return'),
            'vnp_TxnRef' => $txnRef,
        ];

        ksort($inputData);

        $hashData = '';
        $query = '';
        foreach ($inputData as $key => $value) {
            $encodedKey = urlencode((string) $key);
            $encodedValue = urlencode((string) $value);
            $hashData .= ($hashData === '' ? '' : '&')."{$encodedKey}={$encodedValue}";
            $query .= "{$encodedKey}={$encodedValue}&";
        }
        $query = rtrim($query, '&');

        $secureHash = hash_hmac('sha512', $hashData, $hashSecret);

        $withdrawal->update(['payout_reference' => $txnRef]);

        return config('services.vnpay.url').'?'.$query.'&vnp_SecureHash='.$secureHash;
    }


    public function verifySignature(array $params): bool
    {
        $secureHash = $params['vnp_SecureHash'] ?? '';
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        ksort($params);

        $hashData = '';
        foreach ($params as $key => $value) {
            $hashData .= ($hashData === '' ? '' : '&').urlencode((string) $key).'='.urlencode((string) $value);
        }

        $expected = hash_hmac('sha512', $hashData, config('services.vnpay.hash_secret'));

        return hash_equals($expected, (string) $secureHash);
    }
}
