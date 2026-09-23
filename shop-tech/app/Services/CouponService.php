<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponClaim;
use App\Models\CouponRedemption;
use App\Models\User;
use RuntimeException;

/**
 * Validate + tính số tiền giảm cho 1 mã coupon. Luôn tính lại ở BE (không tin
 * discount_amount FE gửi lên) — cả lúc "áp mã xem trước" ở checkout và lúc
 * đặt hàng thật (xem OrderService::place).
 */
class CouponService
{
    public function __construct(private CustomerTierService $tierService) {}

    /**
     * @return array{coupon: Coupon, discount_amount: float, discount_target: string}
     *
     * @throws RuntimeException nếu mã không hợp lệ/hết hạn/chưa đủ điều kiện.
     */
    public function apply(string $code, float $subtotal, ?User $user = null, float $shippingFee = 0.0): array
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

        if ($subtotal < (float) $coupon->min_order_amount) {
            $min = number_format((float) $coupon->min_order_amount, 0, ',', '.');
            throw new RuntimeException("Đơn hàng cần tối thiểu {$min}đ để dùng mã này.");
        }

        // Voucher hạng thành viên: cần đăng nhập, đủ hạng, và đã "bấm nhận" trước.
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
            $discount = min($shippingFee, (float) ($coupon->max_discount ?? $shippingFee));

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
