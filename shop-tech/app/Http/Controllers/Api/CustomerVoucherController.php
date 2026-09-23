<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponClaim;
use App\Models\CouponRedemption;
use App\Services\CustomerTierService;
use Illuminate\Http\Request;

/**
 * "Ví voucher" của khách — chỉ liệt kê voucher HẠNG THÀNH VIÊN (target_tier
 * khác null) mà khách ĐỦ ĐIỀU KIỆN nhận theo hạng hiện tại (xem
 * CustomerTierService). Mã giảm giá công khai (GIAM10...) không hiện ở đây,
 * khách tự nhập ở checkout như trước.
 */
class CustomerVoucherController extends Controller
{
    public function __construct(private CustomerTierService $tierService) {}

    // [GET] /api/vouchers/mine
    public function index(Request $request)
    {
        $user = $request->user();
        $totalSpent = $this->tierService->totalSpentForUser($user->id);
        $userTier = $this->tierService->resolve($totalSpent);
        $userRank = $this->tierService->rank($userTier);

        $coupons = Coupon::query()
            ->whereNotNull('target_tier')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->filter(fn (Coupon $c) => $this->tierService->rank($c->target_tier) <= $userRank)
            ->values();

        $claimedIds = CouponClaim::where('user_id', $user->id)
            ->whereIn('coupon_id', $coupons->pluck('id'))
            ->pluck('coupon_id')
            ->all();

        $redeemedCounts = CouponRedemption::where('user_id', $user->id)
            ->whereIn('coupon_id', $coupons->pluck('id'))
            ->selectRaw('coupon_id, COUNT(*) as c')
            ->groupBy('coupon_id')
            ->pluck('c', 'coupon_id');

        $data = $coupons->map(function (Coupon $coupon) use ($claimedIds, $redeemedCounts) {
            $used = (int) ($redeemedCounts[$coupon->id] ?? 0);

            return [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'title' => $coupon->title,
                'description' => $coupon->description,
                'type' => $coupon->type,
                'is_free_ship' => (bool) $coupon->is_free_ship,
                'value' => (float) $coupon->value,
                'max_discount' => $coupon->max_discount !== null ? (float) $coupon->max_discount : null,
                'min_order_amount' => (float) $coupon->min_order_amount,
                'target_tier' => $coupon->target_tier,
                'target_tier_label' => $this->tierService->label($coupon->target_tier),
                'per_user_limit' => $coupon->per_user_limit,
                'used_count_by_me' => $used,
                'remaining_for_me' => $coupon->per_user_limit !== null ? max(0, $coupon->per_user_limit - $used) : null,
                'expires_at' => $coupon->expires_at,
                'claimed' => in_array($coupon->id, $claimedIds, true),
            ];
        });

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    // [POST] /api/vouchers/{coupon}/claim
    public function claim(Request $request, Coupon $coupon)
    {
        $user = $request->user();

        abort_unless($coupon->is_active && $coupon->target_tier, 422, 'Voucher này không thể nhận trực tiếp.');

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json(['success' => false, 'message' => 'Voucher đã hết hạn.'], 422);
        }

        $userTier = $this->tierService->tierForUser($user);
        if ($this->tierService->rank($userTier) < $this->tierService->rank($coupon->target_tier)) {
            $need = $this->tierService->label($coupon->target_tier);

            return response()->json(['success' => false, 'message' => "Voucher này chỉ dành cho khách hàng hạng {$need} trở lên."], 422);
        }

        $claim = CouponClaim::firstOrCreate(
            ['coupon_id' => $coupon->id, 'user_id' => $user->id],
            ['claimed_at' => now()],
        );

        return response()->json([
            'success' => true,
            'message' => $claim->wasRecentlyCreated ? 'Đã nhận voucher — áp dụng ngay khi thanh toán.' : 'Bạn đã nhận voucher này rồi.',
            'data' => $claim,
        ], 200);
    }
}
