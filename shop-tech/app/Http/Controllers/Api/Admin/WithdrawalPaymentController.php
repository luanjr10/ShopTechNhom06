<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\MomoService;
use App\Services\OnePayService;
use App\Services\PayoutService;
use App\Services\SePayService;
use App\Services\VnpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Admin duyệt rút tiền qua cổng online (momo/vnpay/onepay/sepay) bằng cách đi
 * THẬT sang trang checkout sandbox của cổng đó — giống hệt trải nghiệm khách
 * hàng thanh toán ở checkout (nhập ví/thẻ/OTP test), chứ không chỉ bấm 1 nút
 * là xong. Vì các cổng này KHÔNG có API giải ngân thật (xem PayoutService),
 * "thanh toán" ở đây là admin tự đóng vai người trả tiền trên sandbox để lấy
 * trải nghiệm giao diện thật, sau đó hệ thống MỚI ghi nhận đã giải ngân.
 *
 * create() (auth:api + role:admin) tạo phiên thanh toán, trả `pay_url` để FE
 * điều hướng cả trang sang sandbox. *Return()/sepayRedirect() là route CÔNG
 * KHAI (cổng gọi/redirect trình duyệt về, không có JWT) — xác thực bằng
 * signature riêng từng cổng (giống PaymentController) rồi chốt giải ngân qua
 * PayoutService::finalizeOnlinePayout(), sau đó redirect trình duyệt admin về
 * lại app quản trị.
 */
class WithdrawalPaymentController extends Controller
{
    public function __construct(
        private MomoService $momoService,
        private VnpayService $vnpayService,
        private OnePayService $onepayService,
        private SePayService $sepayService,
        private PayoutService $payoutService,
    ) {}

    // [POST] /api/admin/withdrawals/{withdrawal}/pay
    public function create(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Yêu cầu này đã được xử lý'], 422);
        }
        if ($withdrawal->method === 'cod') {
            return response()->json([
                'success' => false,
                'message' => 'Chuyển khoản ngân hàng không qua cổng thanh toán — dùng nút "Duyệt" trực tiếp.',
            ], 422);
        }

        // Lưu lại admin đang xử lý NGAY LÚC NÀY (route return sau đó là public,
        // không có JWT, không biết ai) — PayoutService::finalizeOnlinePayout()
        // không đụng tới field này nên giá trị ở đây được giữ nguyên.
        $withdrawal->update(['reviewed_by' => $request->user()->id]);

        try {
            $payUrl = match ($withdrawal->method) {
                'momo' => $this->momoService->createWithdrawalPaymentUrl($withdrawal),
                'vnpay' => $this->vnpayService->createWithdrawalPaymentUrl($withdrawal),
                'onepay' => $this->onepayService->createWithdrawalPaymentUrl($withdrawal),
                'sepay' => route('admin.withdrawals.sepay-redirect', ['withdrawal' => $withdrawal->id]),
                default => throw new RuntimeException("Phương thức '{$withdrawal->method}' không được hỗ trợ."),
            };
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }

        return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
    }

    // [GET] /api/payments/momo/withdrawal-return
    public function momoReturn(Request $request)
    {
        $data = $request->query();
        $valid = $this->momoService->verifySignature($data);
        $withdrawal = WithdrawalRequest::where('payout_reference', $data['orderId'] ?? null)->first();

        if ($withdrawal && $valid && (int) ($data['resultCode'] ?? 1) === 0) {
            $this->payoutService->finalizeOnlinePayout($withdrawal);

            return $this->redirectToAdmin($withdrawal->id, 'success');
        }

        Log::warning('MoMo withdrawal-return không hợp lệ hoặc thất bại', $data);

        return $this->redirectToAdmin($withdrawal?->id, 'failed');
    }

    // [GET] /api/payments/vnpay/withdrawal-return
    public function vnpayReturn(Request $request)
    {
        $data = $request->query();
        $valid = $this->vnpayService->verifySignature($data);
        $withdrawal = WithdrawalRequest::where('payout_reference', $data['vnp_TxnRef'] ?? null)->first();

        if ($withdrawal && $valid && ($data['vnp_ResponseCode'] ?? '') === '00') {
            $this->payoutService->finalizeOnlinePayout($withdrawal);

            return $this->redirectToAdmin($withdrawal->id, 'success');
        }

        Log::warning('VNPay withdrawal-return không hợp lệ hoặc thất bại', $data);

        return $this->redirectToAdmin($withdrawal?->id, 'failed');
    }

    // [GET] /api/payments/onepay/withdrawal-return
    public function onepayReturn(Request $request)
    {
        $data = $request->query();
        $valid = $this->onepayService->verifySignature($data);
        $withdrawal = WithdrawalRequest::where('payout_reference', $data['vpc_MerchTxnRef'] ?? null)->first();

        if ($withdrawal && $valid && $this->onepayService->isSuccess($data)) {
            $this->payoutService->finalizeOnlinePayout($withdrawal);

            return $this->redirectToAdmin($withdrawal->id, 'success');
        }

        Log::warning('OnePay withdrawal-return không hợp lệ hoặc thất bại', $data);

        return $this->redirectToAdmin($withdrawal?->id, 'failed');
    }

    // [GET] /api/admin/withdrawals/{withdrawal}/sepay-redirect — trang trung
    // gian auto-submit form POST sang SePay thật (giống PaymentController::sepayRedirect).
    public function sepayRedirect(WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->method !== 'sepay' || $withdrawal->status !== 'pending') {
            abort(404);
        }

        return view('payments.sepay-redirect', $this->sepayService->buildWithdrawalCheckoutForm($withdrawal));
    }

    // [GET] /api/payments/sepay/withdrawal-return/{withdrawal}
    public function sepayReturn(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($request->query('status') === 'success') {
            $this->payoutService->finalizeOnlinePayout($withdrawal);

            return $this->redirectToAdmin($withdrawal->id, 'success');
        }

        return $this->redirectToAdmin($withdrawal->id, 'failed');
    }

    private function redirectToAdmin(?int $withdrawalId, string $status)
    {
        $url = rtrim((string) config('app.admin_url'), '/').'/withdrawals?payout='.$status;
        if ($withdrawalId) {
            $url .= '&id='.$withdrawalId;
        }

        return redirect()->away($url);
    }
}
