<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MomoService;
use App\Services\OnePayService;
use App\Services\OrderService;
use App\Services\SePayService;
use App\Services\SePayServiceStub;
use App\Services\VnpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tạo yêu cầu thanh toán MoMo/VNPay cho 1 Order đã tồn tại (tạo bằng
 * OrderController::place, payment_method=momo|vnpay, status=pending — stock đã
 * trừ ngay từ lúc đặt hàng, xem OrderService::place) + xử lý return/IPN.
 * Return/notify là public (gateway gọi tới, không có JWT) — xác thực bằng
 * signature riêng của từng cổng, KHÔNG dùng auth:api.
 */
class PaymentController extends Controller
{
    public function __construct(
        private MomoService $momoService,
        private VnpayService $vnpayService,
        private OnePayService $onepayService,
        private SePayService $sepayService,
        private OrderService $orderService,
    ) {}

    // [POST] /api/payments/momo/create
    public function momoCreate(Request $request)
    {
        $order = $this->ownedPayableOrder($request, 'momo');
        if ($order instanceof JsonResponse) {
            return $order;
        }

        try {
            $payUrl = $this->momoService->createPaymentUrl($order);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }

        return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
    }

    // [POST] /api/payments/vnpay/create
    public function vnpayCreate(Request $request)
    {
        $order = $this->ownedPayableOrder($request, 'vnpay');
        if ($order instanceof JsonResponse) {
            return $order;
        }

        $payUrl = $this->vnpayService->createPaymentUrl($order);

        return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
    }

    // [POST] /api/payments/onepay/create
    public function onepayCreate(Request $request)
    {
        $order = $this->ownedPayableOrder($request, 'onepay');
        if ($order instanceof JsonResponse) {
            return $order;
        }

        // flow=domestic (ATM nội địa Napas) | international (Visa/Master/JCB).
        $flow = $request->input('flow', 'domestic');
        if (! in_array($flow, ['domestic', 'international'], true)) {
            $flow = 'domestic';
        }

        $payUrl = $this->onepayService->createPaymentUrl($order, $flow);

        return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
    }

    // [POST] /api/payments/sepay/create
    public function sepayCreate(Request $request)
    {
        $order = $this->ownedPayableOrder($request, 'sepay');
        if ($order instanceof JsonResponse) {
            return $order;
        }

        if (config('services.sepay.stub') === true) {
            $payUrl = app(SePayServiceStub::class)->createCheckoutUrl($order);

            return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
        }

        // SePay thật: /v1/checkout/init chỉ nhận SUBMIT FORM (POST), không phải
        // link GET để redirect thẳng — trả về URL trang trung gian tự render
        // form rồi auto-submit (xem sepayRedirect()).
        $this->sepayService->buildCheckoutForm($order);
        $payUrl = route('payments.sepay.redirect', ['order' => $order->id]);

        return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
    }

    // [GET] /api/payments/sepay/redirect/{order} — public (trình duyệt khách tự
    // điều hướng tới đây bằng pay_url trả về từ sepayCreate). Render form ẩn +
    // auto-submit POST sang host checkout thật của SePay.
    public function sepayRedirect(Order $order)
    {
        if ($order->payment_method !== 'sepay' || $order->status !== 'pending' || $order->paid_at) {
            abort(404);
        }

        return view('payments.sepay-redirect', $this->sepayService->buildCheckoutForm($order));
    }

    // [GET] /api/payments/momo/return
    public function momoReturn(Request $request)
    {
        $data = $request->query();
        $valid = $this->momoService->verifySignature($data);
        $order = Order::where('payment_ref', $data['orderId'] ?? null)->first();

        if ($order && $valid && (int) ($data['resultCode'] ?? 1) === 0) {
            $this->markPaid($order);

            return $this->redirectToFrontend($order->id, 'success');
        }

        if ($order) {
            $this->cancelUnpaid($order);
        }

        Log::warning('MoMo return không hợp lệ hoặc thanh toán thất bại', $data);

        return $this->redirectToFrontend($order?->id, 'failed');
    }

    // [POST] /api/payments/momo/notify — IPN server-to-server (best effort, không bắt buộc chạy được trên localhost).
    public function momoNotify(Request $request)
    {
        $data = $request->all();
        $valid = $this->momoService->verifySignature($data);
        $order = Order::where('payment_ref', $data['orderId'] ?? null)->first();

        if ($order && $valid && (int) ($data['resultCode'] ?? 1) === 0) {
            $this->markPaid($order);
        } elseif ($order) {
            $this->cancelUnpaid($order);
        }

        return response()->json(['partnerCode' => $data['partnerCode'] ?? null, 'resultCode' => 0, 'message' => 'success'], 200);
    }

    // [GET] /api/payments/vnpay/return
    public function vnpayReturn(Request $request)
    {
        $data = $request->query();
        $valid = $this->vnpayService->verifySignature($data);
        $order = Order::where('payment_ref', $data['vnp_TxnRef'] ?? null)->first();

        if ($order && $valid && ($data['vnp_ResponseCode'] ?? '') === '00') {
            $this->markPaid($order);

            return $this->redirectToFrontend($order->id, 'success');
        }

        if ($order) {
            $this->cancelUnpaid($order);
        }

        Log::warning('VNPay return không hợp lệ hoặc thanh toán thất bại', $data);

        return $this->redirectToFrontend($order?->id, 'failed');
    }

    // [GET] /api/payments/onepay/return
    public function onepayReturn(Request $request)
    {
        $data = $request->query();
        $valid = $this->onepayService->verifySignature($data);
        $order = Order::where('payment_ref', $data['vpc_MerchTxnRef'] ?? null)->first();

        if ($order && $valid && $this->onepayService->isSuccess($data)) {
            $this->markPaid($order);

            return $this->redirectToFrontend($order->id, 'success');
        }

        if ($order) {
            $this->cancelUnpaid($order);
        }

        Log::warning('OnePay return không hợp lệ hoặc thanh toán thất bại', $data);

        return $this->redirectToFrontend($order?->id, 'failed');
    }

    // [GET] /api/payments/sepay/return
    // SePay redirect khách về đây sau khi thanh toán (success_url/error_url/
    // cancel_url — phân biệt bằng query `status` do CHÍNH MÌNH gắn lúc build
    // form, SePay không tự thêm tham số). CÁC URL NÀY KHÔNG ĐƯỢC KÝ, ai cũng tự
    // gọi được -> chỉ dùng cho UX (hiện "đang chờ xác nhận"), TUYỆT ĐỐI không
    // mark paid ở đây. Trạng thái thật CHỈ đến từ IPN (xem sepayWebhook()).
    public function sepayReturn(Request $request)
    {
        $orderId = $request->query('order');
        $order = $orderId ? Order::find($orderId) : null;

        return $this->redirectToFrontend($order?->id, $order ? 'pending' : 'failed');
    }

    // [POST] /api/payments/sepay/webhook — IPN thật từ SePay Payment Gateway.
    // Verify header `X-Secret-Key` (Auth Type "Secret Key" cấu hình trên
    // dashboard IPN) — SePay không ký HMAC toàn payload như MoMo/VNPay/OnePay.
    public function sepayWebhook(Request $request)
    {
        if (! $this->sepayService->verifyIpnAuth($request->header('X-Secret-Key'))) {
            Log::warning('SePay IPN auth không hợp lệ');

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $order = $this->sepayService->findOrderFromIpn($payload);

        if (! $order) {
            Log::warning('SePay IPN không khớp order nào', $payload);

            // Trả 200 để SePay không retry — chỉ những đơn do mình tạo mới match.
            return response()->json(['success' => true, 'message' => 'ignored'], 200);
        }

        // Idempotent: nếu đơn đã paid thì bỏ qua (IPN có thể gửi trùng).
        if ($this->sepayService->isOrderPaid($payload) && $order->status === 'pending' && ! $order->paid_at) {
            // Đối soát số tiền — tránh khớp nhầm/giả mạo order_invoice_number.
            $received = $this->sepayService->ipnAmount($payload);
            $expected = (float) $order->total_amount;
            if ($received < $expected) {
                Log::warning('SePay IPN số tiền không khớp', [
                    'order_id' => $order->id,
                    'expected' => $expected,
                    'received' => $received,
                ]);

                return response()->json(['success' => false, 'message' => 'amount mismatch'], 200);
            }

            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'sepay_transaction_id' => $this->sepayService->ipnTransactionId($payload) ?? $order->sepay_transaction_id,
            ]);
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * @return Order|JsonResponse
     */
    private function ownedPayableOrder(Request $request, string $method)
    {
        $validated = $request->validate(['order_id' => 'required|integer']);
        $order = Order::find($validated['order_id']);

        if (! $order || $order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }
        if ($order->payment_method !== $method) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng không dùng phương thức thanh toán này'], 422);
        }
        if ($order->status !== 'pending' || $order->paid_at) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng không ở trạng thái chờ thanh toán'], 422);
        }

        return $order;
    }

    private function markPaid(Order $order): void
    {
        if ($order->paid_at || $order->status !== 'pending') {
            return; // đã xử lý trước đó (return + IPN có thể trùng nhau) — idempotent
        }

        $order->update(['status' => 'paid', 'paid_at' => now()]);
    }

    private function cancelUnpaid(Order $order): void
    {
        if ($order->status !== 'pending' || $order->paid_at) {
            return;
        }

        try {
            $this->orderService->cancel($order);
        } catch (RuntimeException $e) {
            Log::error('Không tự hủy được đơn thanh toán thất bại: '.$e->getMessage(), ['order_id' => $order->id]);
        }
    }

    // [POST] /api/payments/sepay/webhook/stub — webhook nội bộ khi SEPAY_STUB=true.
    // Render JSON cho test bằng curl / Postman (route Blade /sepay-stub/checkout
    // dùng cho flow bấm nút). Cùng logic markPaid như webhook thật.
    public function sepayStubWebhook(Request $request)
    {
        if (config('services.sepay.stub') !== true) {
            abort(404);
        }

        $stub = app(SePayServiceStub::class);
        $result = $stub->handleStubWebhook($request->all());

        return response()->json($result, $result['status'] ?? 200);
    }

    private function redirectToFrontend(?int $orderId, string $status)
    {
        $url = rtrim(config('app.frontend_url'), '/').'/thanh-toan/ket-qua?status='.$status;
        if ($orderId) {
            $url .= '&order_id='.$orderId;
        }

        return redirect()->away($url);
    }
}
