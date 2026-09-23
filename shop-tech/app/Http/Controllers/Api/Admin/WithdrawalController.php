<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\PayoutService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Admin duyệt/từ chối yêu cầu rút tiền của seller.
 */
class WithdrawalController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private PayoutService $payoutService,
    ) {}

    // [GET] /api/admin/withdrawals?status=pending
    public function index(Request $request)
    {
        $query = WithdrawalRequest::with([
            'sellerProfile.user:id,name,username,email',
            'reviewer:id,name',
        ])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [POST] /api/admin/withdrawals/{withdrawal}/approve — CHỈ dùng cho
    // method='cod' (chuyển khoản thủ công). Các kênh online (momo/vnpay/
    // onepay/sepay) phải qua WithdrawalPaymentController::create() để sang
    // sandbox thật trước, KHÔNG duyệt trực tiếp ở đây (xem PayoutService).
    public function approve(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Yêu cầu này đã được xử lý'], 422);
        }

        try {
            $this->payoutService->approveCod($withdrawal, $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã duyệt yêu cầu rút tiền',
            'data' => $withdrawal->fresh(),
        ], 200);
    }

    // [POST] /api/admin/withdrawals/{withdrawal}/reject
    public function reject(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Yêu cầu này đã được xử lý'], 422);
        }

        DB::transaction(function () use ($request, $withdrawal) {
            $withdrawal->update([
                'status' => 'rejected',
                'note' => $request->input('note', $withdrawal->note),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // Trả lại phần đã giữ chỗ vào withdrawable.
            $wallet = $withdrawal->sellerProfile->wallet;
            if ($wallet) {
                $this->walletService->refundWithdrawal($wallet, (float) $withdrawal->amount, 'withdrawal_request', $withdrawal->id);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối yêu cầu rút tiền',
            'data' => $withdrawal->fresh(),
        ], 200);
    }
}
