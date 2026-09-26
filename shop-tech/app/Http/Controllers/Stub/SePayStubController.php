<?php

namespace App\Http\Controllers\Stub;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\SePayServiceStub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SePayStubController extends Controller
{
    public function __construct(
        private SePayServiceStub $stub,
        private OrderService $orderService,
    ) {}

    public function checkout(Request $request)
    {
        if (! $this->isStubEnabled()) {
            abort(404);
        }

        $orderId = (int) $request->query('order_id', 0);
        $order = $orderId > 0 ? Order::find($orderId) : null;
        if (! $order || $order->payment_method !== 'sepay') {
            abort(404, 'Order không hợp lệ hoặc không phải SePay.');
        }

        return view('stub.sepay-checkout', [
            'order' => $order,
            'amount' => (int) $request->query('amount', (int) round((float) $order->total_amount)),
            'ref' => (string) $request->query('ref', $order->payment_ref ?? ''),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        if (! $this->isStubEnabled()) {
            abort(404);
        }

        $orderId = (int) $request->input('order_id', 0);
        $order = $orderId > 0 ? Order::find($orderId) : null;
        if (! $order || $order->payment_method !== 'sepay') {
            abort(404);
        }

        $result = $this->stub->handleStubWebhook([
            'order_id' => $order->id,
            'transferAmount' => (int) round((float) $order->total_amount),
            'transactionId' => 'STUBTX'.time(),
        ]);

        if (! ($result['ok'] ?? false)) {
            Log::warning('STUB SePay confirm failed', ['order_id' => $order->id, 'result' => $result]);

            return $this->redirectToFrontend($order->id, 'failed');
        }

        return redirect()->route('payments.sepay.return', ['order_invoice_number' => $order->payment_ref]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        if (! $this->isStubEnabled()) {
            abort(404);
        }

        $orderId = (int) $request->input('order_id', 0);
        $order = $orderId > 0 ? Order::find($orderId) : null;
        if (! $order) {
            abort(404);
        }

        if ($order->status === 'pending' && ! $order->paid_at) {
            try {
                $this->orderService->cancel($order);
            } catch (RuntimeException $e) {
                Log::error('Stub SePay cancel failed: '.$e->getMessage(), ['order_id' => $order->id]);
            }
        }

        return $this->redirectToFrontend($order->id, 'failed');
    }

    public function webhook(Request $request)
    {
        if (! $this->isStubEnabled()) {
            abort(404);
        }

        $result = $this->stub->handleStubWebhook($request->all());

        return response()->json($result, $result['status'] ?? 200);
    }

    private function isStubEnabled(): bool
    {
        return config('services.sepay.stub') === true;
    }

    private function redirectToFrontend(?int $orderId, string $status): RedirectResponse
    {
        $url = rtrim(config('app.frontend_url'), '/').'/thanh-toan/ket-qua?status='.$status;
        if ($orderId) {
            $url .= '&order_id='.$orderId;
        }

        return redirect()->away($url);
    }
}
