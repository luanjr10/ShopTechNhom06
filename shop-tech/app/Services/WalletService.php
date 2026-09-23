<?php

namespace App\Services;

use App\Models\SellerWallet;
use App\Models\WalletTransaction;

/**
 * Quản lý số dư ví seller theo 3 trạng thái:
 * - pending_balance: tiền từ đơn CHƯA hoàn thành (đang giữ chỗ).
 * - withdrawable_balance: tiền từ đơn ĐÃ hoàn thành, có thể rút.
 * - balance: tổng tiền đang được ghi nợ cho seller (pending + withdrawable).
 * Mỗi thao tác đều ghi 1 WalletTransaction để truy vết.
 */
class WalletService
{
    /** Đơn được đặt: giữ tiền net vào pending. */
    public function hold(SellerWallet $wallet, float $amount, string $refType, int $refId, ?string $desc = null): void
    {
        $wallet->pending_balance += $amount;
        $wallet->balance += $amount;
        $wallet->save();

        $this->log($wallet, 'hold', $amount, $refType, $refId, $desc ?? 'Giữ tiền đơn hàng');
    }

    /** Đơn hoàn thành: chuyển từ pending sang withdrawable. */
    public function release(SellerWallet $wallet, float $amount, string $refType, int $refId, ?string $desc = null): void
    {
        $wallet->pending_balance = max(0, $wallet->pending_balance - $amount);
        $wallet->withdrawable_balance += $amount;
        $wallet->save();

        $this->log($wallet, 'release', $amount, $refType, $refId, $desc ?? 'Đơn hoàn thành, tiền có thể rút');
    }

    /** Đơn bị hủy trước khi hoàn thành: hoàn lại phần đã giữ trong pending. */
    public function reverseHold(SellerWallet $wallet, float $amount, string $refType, int $refId, ?string $desc = null): void
    {
        $wallet->pending_balance = max(0, $wallet->pending_balance - $amount);
        $wallet->balance = max(0, $wallet->balance - $amount);
        $wallet->save();

        $this->log($wallet, 'refund', $amount, $refType, $refId, $desc ?? 'Hủy đơn, hoàn phần giữ chỗ');
    }

    /** Seller tạo yêu cầu rút: giữ chỗ khỏi withdrawable để tránh rút trùng. */
    public function reserveForWithdrawal(SellerWallet $wallet, float $amount, string $refType, int $refId): void
    {
        $wallet->withdrawable_balance = max(0, $wallet->withdrawable_balance - $amount);
        $wallet->save();

        $this->log($wallet, 'hold', $amount, $refType, $refId, 'Giữ chỗ yêu cầu rút tiền');
    }

    /** Admin duyệt rút: trừ khỏi tổng (tiền đã chi ra ngoài). */
    public function payout(SellerWallet $wallet, float $amount, string $refType, int $refId): void
    {
        $wallet->balance = max(0, $wallet->balance - $amount);
        $wallet->save();

        $this->log($wallet, 'debit', $amount, $refType, $refId, 'Rút tiền được duyệt');
    }

    /** Admin từ chối rút: trả lại withdrawable. */
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
