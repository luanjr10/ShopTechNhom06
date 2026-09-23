<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerWallet;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Seller tạo yêu cầu rút tiền từ số dư withdrawable. Admin duyệt ở Admin\WithdrawalController.
 */
class WithdrawalController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    // [GET] /api/seller/withdrawals
    public function index(Request $request)
    {
        $profile = $request->user()->sellerProfile;

        return response()->json([
            'success' => true,
            'data' => WithdrawalRequest::where('seller_profile_id', $profile->id)
                ->latest()
                ->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [POST] /api/seller/withdrawals
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            // Kênh nhận tiền — cùng danh sách payment_method của checkout (xem
            // App\Services\PayoutService). bank_account/bank_name dùng chung cho
            // mọi kênh: số tài khoản/SĐT ví + tên ngân hàng/ví tương ứng.
            'method' => 'required|in:cod,momo,vnpay,onepay,sepay',
            'bank_account' => 'required|string|max:50',
            'bank_name' => 'required|string|max:100',
            'note' => 'nullable|string|max:255',
        ]);

        $profile = $request->user()->sellerProfile;
        $wallet = SellerWallet::firstOrCreate(['seller_profile_id' => $profile->id]);

        if ($validated['amount'] > (float) $wallet->withdrawable_balance) {
            return response()->json([
                'success' => false,
                'message' => 'Số tiền rút vượt quá số dư có thể rút',
            ], 422);
        }

        $withdrawal = DB::transaction(function () use ($validated, $profile, $wallet) {
            $withdrawal = WithdrawalRequest::create([
                'seller_profile_id' => $profile->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'status' => 'pending',
                'bank_account' => $validated['bank_account'],
                'bank_name' => $validated['bank_name'],
                'note' => $validated['note'] ?? null,
            ]);

            // Giữ chỗ khỏi withdrawable để không rút trùng.
            $this->walletService->reserveForWithdrawal(
                $wallet, (float) $validated['amount'], 'withdrawal_request', $withdrawal->id
            );

            return $withdrawal;
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu rút tiền',
            'data' => $withdrawal,
        ], 201);
    }
}
