<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponClaim;
use App\Models\CouponRedemption;
use App\Models\User;
use RuntimeException;

class CouponService
{
    public function __construct(private CustomerTierService $tierService) {}

    public static function weekdayLabel(int $weekday): string
    {
        return $weekday === 0 ? 'Chủ Nhật' : 'Thứ '.($weekday + 1);
    }

    /**
     * @param  float|null  $commissionCap  Tổng hoa hồng sàn thu được từ đơn; mức giảm không được vượt quá số này.
     * @param  string|null  $receiverPhone  SĐT nhận hàng, dùng để chặn lách voucher khách mới bằng tài khoản phụ.
     * @return array{coupon: Coupon, discount_amount: float, discount_target: string, capped: bool, original_discount: float}
     */
    public function apply(string $code, float $subtotal, ?User $user = null, float $shippingFee = 0.0, ?float $commissionCap = null, ?string $receiverPhone = null, array $storeSubtotals = [], array $storeShippingFees = []): array
    {
        $result = $this->resolve($code, $subtotal, $user, $shippingFee, $receiverPhone, $storeSubtotals, $storeShippingFees);

        $result['original_discount'] = $result['discount_amount'];
        $result['capped'] = false;

        $isTradeInCredit = $result['coupon']->trade_in_request_id !== null || $result['coupon']->store_id !== null;

        if ($commissionCap !== null && ! $isTradeInCredit && $result['discount_amount'] > $commissionCap) {
            $result['discount_amount'] = round(max(0.0, $commissionCap), 2);
            $result['capped'] = true;
        }

        return $result;
    }

    /**
     * @param  array<int, float>  $storeSubtotals  store_id => tạm tính của gian hàng (voucher do gian hàng phát chỉ tính trên phần này)
     * @param  array<int, float>  $storeShippingFees  store_id => phí ship của gian hàng (voucher miễn ship của gian hàng chỉ giảm phần này)
     */
    private function resolve(string $code, float $subtotal, ?User $user, float $shippingFee, ?string $receiverPhone, array $storeSubtotals = [], array $storeShippingFees = []): array
    {
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])->first();

        if (! $coupon || ! $coupon->is_active) {
            throw new RuntimeException('Mã giảm giá không tồn tại hoặc đã bị khóa.');
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw new RuntimeException('Mã giảm giá đã hết hạn.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new RuntimeException('Mã giảm giá đã hết lượt sử dụng.');
        }

        if ($coupon->user_id !== null && (! $user || $coupon->user_id !== $user->id)) {
            throw new RuntimeException('Mã giảm giá này không dành cho tài khoản của bạn.');
        }

        if ($coupon->store_id !== null) {
            $eligible = (float) ($storeSubtotals[$coupon->store_id] ?? 0);

            if ($eligible <= 0) {
                $storeName = $coupon->store()->value('name') ?? 'đã phát voucher';

                throw new RuntimeException("Voucher này chỉ áp dụng cho sản phẩm của gian hàng {$storeName}.");
            }

            $subtotal = $eligible;
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            $min = number_format((float) $coupon->min_order_amount, 0, ',', '.');
            throw new RuntimeException("Đơn hàng cần tối thiểu {$min}đ để dùng mã này.");
        }

        if ($coupon->new_customer_only) {
            if (! $user) {
                throw new RuntimeException('Bạn cần đăng nhập để dùng voucher này.');
            }

            if (! $user->hasVerifiedEmail()) {
                throw new RuntimeException('Vui lòng xác thực email để dùng voucher dành cho khách hàng mới.');
            }

            if (! Coupon::userIsNewCustomer($user->id) || ($receiverPhone && Coupon::phoneHasOrdered($receiverPhone))) {
                throw new RuntimeException('Voucher này chỉ dành cho khách hàng mới, áp dụng cho đơn hàng đầu tiên.');
            }
        }

        if ($coupon->isDaily()) {
            if ($coupon->weekday !== Coupon::todayWeekday()) {
                throw new RuntimeException('Voucher này chỉ áp dụng vào '.self::weekdayLabel($coupon->weekday).' hàng tuần.');
            }

            if (! $user) {
                throw new RuntimeException('Bạn cần đăng nhập để dùng voucher này.');
            }

            $startOfToday = now('Asia/Ho_Chi_Minh')->startOfDay();

            if ($coupon->isSoldOutToday()) {
                throw new RuntimeException('Voucher hôm nay đã hết lượt, hẹn bạn tuần sau.');
            }

            $usedToday = CouponRedemption::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->where('created_at', '>=', $startOfToday)
                ->exists();
            if ($usedToday) {
                throw new RuntimeException('Bạn đã dùng voucher này hôm nay rồi, hẹn bạn tuần sau.');
            }
        }

        if ($coupon->isTierRestricted()) {
            if (! $user) {
                throw new RuntimeException('Bạn cần đăng nhập để dùng voucher này.');
            }

            $userTier = $this->tierService->tierForUser($user);
            if ($this->tierService->rank($userTier) < $this->tierService->rank($coupon->target_tier)) {
                $need = $this->tierService->label($coupon->target_tier);
                throw new RuntimeException("Voucher này chỉ dành cho khách hàng hạng {$need} trở lên.");
            }

            $claimed = CouponClaim::where('coupon_id', $coupon->id)->where('user_id', $user->id)->exists();
            if (! $claimed) {
                throw new RuntimeException('Vui lòng bấm "Nhận voucher" trước khi áp dụng.');
            }
        }

        if ($coupon->per_user_limit !== null) {
            if (! $user) {
                throw new RuntimeException('Bạn cần đăng nhập để dùng voucher này.');
            }

            $usedByUser = CouponRedemption::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count();
            if ($usedByUser >= $coupon->per_user_limit) {
                throw new RuntimeException('Bạn đã dùng hết lượt cho voucher này.');
            }
        }

        if ($coupon->is_free_ship) {
            $shippingBase = $coupon->store_id !== null ? (float) ($storeShippingFees[$coupon->store_id] ?? 0) : $shippingFee;
            $discount = min($shippingBase, (float) ($coupon->max_discount ?? $shippingBase));

            return ['coupon' => $coupon, 'discount_amount' => round($discount, 2), 'discount_target' => 'shipping'];
        }

        $discount = $coupon->type === 'percent'
            ? $subtotal * ((float) $coupon->value / 100)
            : (float) $coupon->value;

        if ($coupon->type === 'percent' && $coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        $discount = min(round($discount, 2), $subtotal);

        return ['coupon' => $coupon, 'discount_amount' => $discount, 'discount_target' => 'subtotal'];
    }
}
