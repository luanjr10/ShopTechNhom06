<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WithdrawalRequest;

class SePayService
{
    private const SIGNED_FIELDS = [
        'order_amount', 'merchant', 'currency', 'operation',
        'order_description', 'order_invoice_number', 'customer_id',
        'payment_method', 'success_url', 'error_url', 'cancel_url',
    ];

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

    public function verifyIpnAuth(?string $secretKeyHeader): bool
    {
        $expected = config('services.sepay.ipn_secret');
        if (! $expected || ! $secretKeyHeader) {
            return false;
        }

        return hash_equals((string) $expected, $secretKeyHeader);
    }

    public function findOrderFromIpn(array $payload): ?Order
    {
        $invoiceNumber = $payload['order']['order_invoice_number'] ?? null;
        if (! $invoiceNumber) {
            return null;
        }

        return Order::where('payment_ref', $invoiceNumber)->first();
    }

    public function isOrderPaid(array $payload): bool
    {
        return ($payload['notification_type'] ?? null) === 'ORDER_PAID';
    }

    public function ipnAmount(array $payload): float
    {
        return (float) ($payload['order']['order_amount'] ?? 0);
    }

    public function ipnTransactionId(array $payload): ?string
    {
        $id = $payload['transaction']['transaction_id'] ?? null;

        return $id !== null ? (string) $id : null;
    }

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
