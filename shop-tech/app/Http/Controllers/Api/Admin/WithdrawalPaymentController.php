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

class WithdrawalPaymentController extends Controller
{
    public function __construct(
        private MomoService $momoService,
        private VnpayService $vnpayService,
        private OnePayService $onepayService,
        private SePayService $sepayService,
        private PayoutService $payoutService,
    ) {}

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

    public function sepayRedirect(WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->method !== 'sepay' || $withdrawal->status !== 'pending') {
            abort(404);
        }

        return view('payments.sepay-redirect', $this->sepayService->buildWithdrawalCheckoutForm($withdrawal));
    }

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
