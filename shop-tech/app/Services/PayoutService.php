<?php

namespace App\Services;

use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayoutService
{
    public function __construct(private WalletService $walletService) {}

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
