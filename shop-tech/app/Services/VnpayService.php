<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;

/**
 * Cổng thanh toán VNPay (sandbox.vnpayment.vn, paymentv2). Chữ ký sha512 trên
 * chuỗi các tham số đã ksort + urlencode — đúng theo tài liệu VNPay, dùng
 * CHUNG công thức để tạo yêu cầu và verify lúc return.
 *
 * Lưu ý timezone: VNPay yêu cầu vnp_CreateDate / vnp_ExpireDate theo giờ
 * GMT+7 (Asia/Ho_Chi_Minh). Nếu truyền UTC -> server VNPay tính thời điểm
 * tạo nằm ở "tương lai" so với giờ VN hiện tại -> trả mã lỗi 07 (Expired
 * transaction) dù đơn vừa mới tạo vài giây.
 */
class VnpayService
{
    /**
     * Múi giờ VNPay dùng để parse YmdHis (giờ Việt Nam, GMT+7).
     */
    private const VNPAY_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function createPaymentUrl(Order $order): string
    {
        $tmnCode = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');

        $txnRef = 'ORDER'.$order->id.'-'.time();
        $amount = (int) round((float) $order->total_amount) * 100; // VNPay yêu cầu x100 (không có phần lẻ)

        // Luôn gửi theo giờ VN (+7), không phụ thuộc APP_TIMEZONE (UTC).
        $nowVn = CarbonImmutable::now(self::VNPAY_TIMEZONE);

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $amount,
            'vnp_CreateDate' => $nowVn->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            // VNPay quy định tối đa 15 phút kể từ CreateDate.
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

    /**
     * Cùng luồng createPaymentUrl() nhưng cho ADMIN duyệt rút tiền — xem
     * doc-block MomoService::createWithdrawalPaymentUrl().
     */
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

    /**
     * @param  array<string, string>  $params  Toàn bộ query string vnp_* (chưa bỏ vnp_SecureHash).
     */
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
