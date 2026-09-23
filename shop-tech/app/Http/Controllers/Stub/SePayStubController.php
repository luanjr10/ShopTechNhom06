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

/**
 * Controller cho SePay stub — chỉ mount khi services.sepay.stub=true (env
 * SEPAY_STUB=true). Trang '/sepay-stub/checkout' giả lập trang checkout của
 * SePay thật: hiển thị QR giả + 2 nút "Đã thanh toán" / "Hủy" — KH sẽ bấm
 * để trigger webhook giả tới hệ thống.
 *
 * Trang này KHÔNG check auth vì nó là mô phỏng gateway — sau khi merchant
 * thật đăng ký SePay, controller này không được mount nữa và SePay thật sẽ
 * redirect khách tới my.sepay.vn.
 */
class SePayStubController extends Controller
{
    public function __construct(
        private SePayServiceStub $stub,
        private OrderService $orderService,
    ) {}

    /**
     * Trang checkout giả: hiển thị QR + 2 nút.
     */
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

    /**
     * Nút "Đã thanh toán" -> gọi webhook stub -> mark order paid -> redirect
     * về trang /thanh-toan/ket-qua (giống SePay thật redirect khách về return_url).
     */
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

        // SePay thật sẽ gọi return_url sau khi khách bấm "Đã thanh toán" — stub
        // cũng vậy.
        return redirect()->route('payments.sepay.return', ['order_invoice_number' => $order->payment_ref]);
    }

    /**
     * Nút "Hủy" -> hủy order -> redirect về trang kết quả failed.
     */
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

    /**
     * Webhook endpoint stub — POST /api/payments/sepay/webhook/stub.
     * For tests via curl, không qua Blade. Dùng chung logic với confirm().
     */
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
