<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SePayServiceStub
{
    public function createCheckoutUrl(Order $order): string
    {
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

    public function verifyWebhookAuth(?string $authHeader): bool
    {
        return true;
    }

    public function findOrderFromWebhook(array $payload): ?Order
    {
        $orderId = (int) ($payload['order_id'] ?? 0);

        return $orderId > 0 ? Order::find($orderId) : null;
    }

    public function handleStubWebhook(array $payload): array
    {
        $order = $this->findOrderFromWebhook($payload);
        if (! $order) {
            return ['ok' => false, 'message' => 'order not found', 'status' => 200];
        }

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
