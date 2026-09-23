<?php

namespace App\Services;

use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Giải ngân yêu cầu rút tiền của seller qua kênh seller đã chọn lúc tạo yêu
 * cầu (cùng danh sách payment_method của checkout: cod/momo/vnpay/onepay/sepay).
 *
 * QUAN TRỌNG (sandbox, không phải thật): MoMo/VNPay/OnePay/SePay tích hợp
 * trong dự án này (xem PaymentController/MomoService/VnpayService/OnePayService/
 * SePayService) CHỈ có API "pay-in" — thu tiền TỪ khách hàng — không cổng nào
 * cấp API "giải ngân"/disbursement công khai để CHUYỂN tiền RA cho 1 cá nhân
 * (cần hợp đồng doanh nghiệp riêng, không có trong sandbox miễn phí). Để vẫn
 * cho admin THẤY giao diện sandbox thật của từng cổng, luồng online đưa admin
 * sang thẳng trang checkout sandbox (như thể admin là người "thanh toán")
 * qua WithdrawalPaymentController — payout() ở đây chỉ còn xử lý 'cod' (chuyển
 * khoản thủ công, admin tự chuyển tiền thật bên ngoài).
 */
class PayoutService
{
    public function __construct(private WalletService $walletService) {}

    /**
     * Duyệt trực tiếp — CHỈ dùng cho 'cod'. Các kênh online phải đi qua
     * WithdrawalPaymentController (sang sandbox thật rồi mới finalize()).
     */
    public function approveCod(WithdrawalRequest $withdrawal, int $reviewedBy): void
    {
        if ($withdrawal->method !== 'cod') {
            throw new RuntimeException("Phương thức '{$withdrawal->method}' phải duyệt qua cổng thanh toán sandbox, không dùng nút Duyệt trực tiếp.");
        }

        DB::transaction(function () use ($withdrawal, $reviewedBy) {
            $withdrawal->update([
                'status' => 'approved',
                'paid_at' => now(),
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
            ]);

            $wallet = $withdrawal->sellerProfile->wallet;
            if ($wallet) {
                $this->walletService->payout($wallet, (float) $withdrawal->amount, 'withdrawal_request', $withdrawal->id);
            }
        });
    }

    /**
     * Chốt giải ngân sau khi admin hoàn tất checkout trên sandbox online
     * (gọi từ *Return() của WithdrawalPaymentController). Idempotent — nếu
     * đã approved rồi (return gọi trùng) thì bỏ qua, không payout 2 lần.
     */
    public function finalizeOnlinePayout(WithdrawalRequest $withdrawal): void
    {
        if ($withdrawal->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($withdrawal) {
            $withdrawal->update([
                'status' => 'approved',
                'paid_at' => now(),
                'reviewed_at' => now(),
            ]);

            $wallet = $withdrawal->sellerProfile->wallet;
            if ($wallet) {
                $this->walletService->payout($wallet, (float) $withdrawal->amount, 'withdrawal_request', $withdrawal->id);
            }
        });
    }
}
