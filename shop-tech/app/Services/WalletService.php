<?php

namespace App\Services;

use App\Models\SellerWallet;
use App\Models\WalletTransaction;

class WalletService
{
    public function hold(SellerWallet $wallet, float $amount, string $refType, int $refId, ?string $desc = null): void
    {
        $wallet->pending_balance += $amount;
        $wallet->balance += $amount;
        $wallet->save();

        $this->log($wallet, 'hold', $amount, $refType, $refId, $desc ?? 'Giữ tiền đơn hàng');
    }

    public function release(SellerWallet $wallet, float $amount, string $refType, int $refId, ?string $desc = null): void
    {
        $wallet->pending_balance = max(0, $wallet->pending_balance - $amount);
        $wallet->withdrawable_balance += $amount;
        $wallet->save();

        $this->log($wallet, 'release', $amount, $refType, $refId, $desc ?? 'Đơn hoàn thành, tiền có thể rút');
    }

    public function reverseHold(SellerWallet $wallet, float $amount, string $refType, int $refId, ?string $desc = null): void
    {
        $wallet->pending_balance = max(0, $wallet->pending_balance - $amount);
        $wallet->balance = max(0, $wallet->balance - $amount);
        $wallet->save();

        $this->log($wallet, 'refund', $amount, $refType, $refId, $desc ?? 'Hủy đơn, hoàn phần giữ chỗ');
    }

    public function reserveForWithdrawal(SellerWallet $wallet, float $amount, string $refType, int $refId): void
    {
        $wallet->withdrawable_balance = max(0, $wallet->withdrawable_balance - $amount);
        $wallet->save();

        $this->log($wallet, 'hold', $amount, $refType, $refId, 'Giữ chỗ yêu cầu rút tiền');
    }

    public function payout(SellerWallet $wallet, float $amount, string $refType, int $refId): void
    {
        $wallet->balance = max(0, $wallet->balance - $amount);
        $wallet->save();

        $this->log($wallet, 'debit', $amount, $refType, $refId, 'Rút tiền được duyệt');
    }

    public function refundWithdrawal(SellerWallet $wallet, float $amount, string $refType, int $refId): void
    {
        $wallet->withdrawable_balance += $amount;
        $wallet->save();

        $this->log($wallet, 'refund', $amount, $refType, $refId, 'Từ chối rút, hoàn tiền vào ví');
    }

    private function log(SellerWallet $wallet, string $type, float $amount, string $refType, int $refId, string $desc): void
    {
        WalletTransaction::create([
            'seller_wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'description' => $desc,
        ]);
    }
}
