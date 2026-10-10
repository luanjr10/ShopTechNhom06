<?php

namespace App\Services;

use App\Models\DailyCheckin;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Models\User;
use App\Models\XuTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ShopTech Xu: 1 xu = 1đ khi thanh toán. Xu là chi phí marketing của sàn nên mức dùng
 * bị giới hạn bởi tỉ lệ đơn hàng và phần hoa hồng sàn còn lại sau voucher.
 */
class XuService
{
    public const EARN_PERCENT_OF_ORDER = 1;

    public const CHECKIN_BASE = 100;

    public const CHECKIN_STREAK_BONUS = 500;

    public const CHECKIN_STREAK_CYCLE = 7;

    public const REVIEW_WITH_PHOTO = 200;

    public const REVIEW_TEXT_ONLY = 50;

    public const MAX_REDEEM_PERCENT = 50;

    public function earnForSellerOrder(SellerOrder $sellerOrder): ?XuTransaction
    {
        $amount = (int) floor($sellerOrder->netProductAmount() * self::EARN_PERCENT_OF_ORDER / 100);

        if ($amount <= 0) {
            return null;
        }

        return $this->credit(
            (int) $sellerOrder->order->user_id,
            'earn_order',
            $amount,
            'seller_order',
            $sellerOrder->id,
            "Thưởng xu đơn hàng #{$sellerOrder->order_id}",
        );
    }

    public function reverseEarnForSellerOrder(int $sellerOrderId): ?XuTransaction
    {
        $earned = XuTransaction::where('type', 'earn_order')
            ->where('reference_type', 'seller_order')
            ->where('reference_id', $sellerOrderId)
            ->first();

        if (! $earned) {
            return null;
        }

        return DB::transaction(function () use ($earned, $sellerOrderId) {
            $user = User::whereKey($earned->user_id)->lockForUpdate()->first();
            $amount = min($earned->amount, (int) $user->xu_balance);

            if ($amount <= 0 || XuTransaction::where('user_id', $user->id)->where('type', 'reverse_earn')
                ->where('reference_type', 'seller_order')->where('reference_id', $sellerOrderId)->exists()) {
                return null;
            }

            return $this->write($user, 'reverse_earn', -$amount, 'seller_order', $sellerOrderId, 'Thu hồi xu do đơn hoàn trả');
        });
    }

    public function earnForReview(ProductReview $review): ?XuTransaction
    {
        if (! $review->order_item_id) {
            return null;
        }

        $hasPhoto = ! empty($review->images);

        return $this->credit(
            (int) $review->user_id,
            'earn_review',
            $hasPhoto ? self::REVIEW_WITH_PHOTO : self::REVIEW_TEXT_ONLY,
            'review',
            $review->id,
            $hasPhoto ? 'Thưởng đánh giá có ảnh' : 'Thưởng đánh giá sản phẩm',
        );
    }

    /**
     * @return array{xu: int, streak: int, bonus: bool}
     */
    public function checkIn(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $today = now('Asia/Ho_Chi_Minh')->startOfDay();

            if (DailyCheckin::where('user_id', $user->id)->whereDate('checkin_date', $today->toDateString())->exists()) {
                throw new RuntimeException('Hôm nay bạn đã điểm danh rồi, mai quay lại nhé!');
            }

            $yesterday = DailyCheckin::where('user_id', $user->id)
                ->whereDate('checkin_date', $today->copy()->subDay()->toDateString())
                ->first();

            $streak = ($yesterday?->streak ?? 0) + 1;
            $bonus = $streak % self::CHECKIN_STREAK_CYCLE === 0;
            $xu = self::CHECKIN_BASE + ($bonus ? self::CHECKIN_STREAK_BONUS : 0);

            DailyCheckin::create([
                'user_id' => $user->id,
                'checkin_date' => $today->toDateString(),
                'streak' => $streak,
                'xu_earned' => $xu,
            ]);

            $this->credit(
                $user->id,
                'earn_checkin',
                $xu,
                'checkin',
                (int) $today->format('Ymd'),
                $bonus ? "Điểm danh ngày thứ {$streak} — thưởng chuỗi" : 'Điểm danh hằng ngày',
            );

            return ['xu' => $xu, 'streak' => $streak, 'bonus' => $bonus];
        });
    }

    /**
     * @return array{balance: int, checked_in_today: bool, streak: int, next_reward: int, rules: array<string, int>}
     */
    public function summary(User $user): array
    {
        $today = now('Asia/Ho_Chi_Minh')->startOfDay();

        $latest = DailyCheckin::where('user_id', $user->id)->orderByDesc('checkin_date')->first();
        $checkedToday = $latest && $latest->checkin_date->toDateString() === $today->toDateString();
        $alive = $latest && ($checkedToday || $latest->checkin_date->toDateString() === $today->copy()->subDay()->toDateString());
        $streak = $alive ? $latest->streak : 0;

        $nextStreak = $streak + 1;
        $nextReward = self::CHECKIN_BASE + ($nextStreak % self::CHECKIN_STREAK_CYCLE === 0 ? self::CHECKIN_STREAK_BONUS : 0);

        return [
            'balance' => (int) User::whereKey($user->id)->value('xu_balance'),
            'checked_in_today' => (bool) $checkedToday,
            'streak' => $streak,
            'next_reward' => $nextReward,
            'rules' => [
                'earn_percent_of_order' => self::EARN_PERCENT_OF_ORDER,
                'checkin_base' => self::CHECKIN_BASE,
                'checkin_streak_bonus' => self::CHECKIN_STREAK_BONUS,
                'checkin_streak_cycle' => self::CHECKIN_STREAK_CYCLE,
                'review_with_photo' => self::REVIEW_WITH_PHOTO,
                'review_text_only' => self::REVIEW_TEXT_ONLY,
                'max_redeem_percent' => self::MAX_REDEEM_PERCENT,
            ],
        ];
    }

    /**
     * Số xu tối đa được dùng cho đơn: không quá số dư, 50% giá trị hàng sau voucher,
     * và không vượt phần hoa hồng sàn còn lại sau voucher.
     */
    public function maxRedeemable(User $user, float $productsAfterVoucher, float $commissionHeadroom): int
    {
        $balance = (int) User::whereKey($user->id)->value('xu_balance');
        $byPercent = (int) floor(max(0, $productsAfterVoucher) * self::MAX_REDEEM_PERCENT / 100);

        return max(0, min($balance, $byPercent, (int) floor(max(0, $commissionHeadroom))));
    }

    public function spendOnOrder(User $user, Order $order, int $amount): XuTransaction
    {
        return DB::transaction(function () use ($user, $order, $amount) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ($amount > (int) $locked->xu_balance) {
                throw new RuntimeException('Số dư xu không đủ.');
            }

            return $this->write($locked, 'spend_order', -$amount, 'order', $order->id, "Dùng xu thanh toán đơn #{$order->id}");
        });
    }

    public function refundOrder(Order $order): ?XuTransaction
    {
        if ((int) $order->xu_used <= 0) {
            return null;
        }

        return $this->credit((int) $order->user_id, 'refund_order', (int) $order->xu_used, 'order', $order->id, "Hoàn xu đơn #{$order->id} bị hủy");
    }

    private function credit(int $userId, string $type, int $amount, string $refType, int $refId, string $description): ?XuTransaction
    {
        return DB::transaction(function () use ($userId, $type, $amount, $refType, $refId, $description) {
            $user = User::whereKey($userId)->lockForUpdate()->first();

            if (! $user || XuTransaction::where('user_id', $userId)->where('type', $type)
                ->where('reference_type', $refType)->where('reference_id', $refId)->exists()) {
                return null;
            }

            return $this->write($user, $type, $amount, $refType, $refId, $description);
        });
    }

    private function write(User $user, string $type, int $amount, string $refType, int $refId, string $description): XuTransaction
    {
        $user->forceFill(['xu_balance' => max(0, (int) $user->xu_balance + $amount)])->save();

        return XuTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => (int) $user->xu_balance,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'description' => $description,
        ]);
    }
}
