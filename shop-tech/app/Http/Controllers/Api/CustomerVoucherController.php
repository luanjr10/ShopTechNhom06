<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponClaim;
use App\Models\CouponRedemption;
use App\Services\CustomerTierService;
use Illuminate\Http\Request;

class CustomerVoucherController extends Controller
{
    public function __construct(private CustomerTierService $tierService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $totalSpent = $this->tierService->totalSpentForUser($user->id);
        $userTier = $this->tierService->resolve($totalSpent);
        $userRank = $this->tierService->rank($userTier);

        $isNewCustomer = Coupon::userIsNewCustomer($user->id);
        $today = Coupon::todayWeekday();

        $coupons = Coupon::query()
            ->with('store:id,name')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->get()
            ->filter(function (Coupon $c) use ($userRank, $isNewCustomer, $today) {
                if ($c->trade_in_request_id !== null) {
                    return $c->usage_limit === null || $c->used_count < $c->usage_limit;
                }
                if ($c->target_tier === null && ! $c->new_customer_only && ! $c->isDaily()) {
                    return $c->usage_limit === null || $c->used_count < $c->usage_limit;
                }
                if ($c->new_customer_only) {
                    return $isNewCustomer;
                }
                if ($c->isDaily()) {
                    return $c->weekday === $today;
                }

                return $this->tierService->rank($c->target_tier) <= $userRank;
            })
            ->values();

        $usedTodayIds = CouponRedemption::where('user_id', $user->id)
            ->whereIn('coupon_id', $coupons->pluck('id'))
            ->where('created_at', '>=', now('Asia/Ho_Chi_Minh')->startOfDay())
            ->pluck('coupon_id')
            ->all();

        $claimedIds = CouponClaim::where('user_id', $user->id)
            ->whereIn('coupon_id', $coupons->pluck('id'))
            ->pluck('coupon_id')
            ->all();

        $redeemedCounts = CouponRedemption::where('user_id', $user->id)
            ->whereIn('coupon_id', $coupons->pluck('id'))
            ->selectRaw('coupon_id, COUNT(*) as c')
            ->groupBy('coupon_id')
            ->pluck('c', 'coupon_id');

        $data = $coupons->map(function (Coupon $coupon) use ($claimedIds, $redeemedCounts, $usedTodayIds) {
            $used = (int) ($redeemedCounts[$coupon->id] ?? 0);
            $kind = $coupon->trade_in_request_id !== null
                ? 'trade_in'
                : ($coupon->new_customer_only ? 'new_customer' : ($coupon->isDaily() ? 'daily' : ($coupon->target_tier === null ? 'public' : 'tier')));
            $remaining = $coupon->per_user_limit !== null ? max(0, $coupon->per_user_limit - $used) : null;

            $soldOut = $kind === 'daily' && $coupon->isSoldOutToday();

            if ($kind === 'daily' && in_array($coupon->id, $usedTodayIds, true)) {
                $remaining = 0;
            }

            return [
                'kind' => $kind,
                'requires_claim' => $kind === 'tier',
                'sold_out' => $soldOut,
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
                'store_id' => $coupon->store_id,
                'store_name' => $coupon->store?->name,
                'target_tier_label' => $coupon->target_tier ? $this->tierService->label($coupon->target_tier) : null,
                'per_user_limit' => $coupon->per_user_limit,
                'used_count_by_me' => $used,
                'remaining_for_me' => $remaining,
                'expires_at' => $coupon->expires_at,
                'claimed' => $kind !== 'tier' || in_array($coupon->id, $claimedIds, true),
            ];
        });

        return response()->json(['success' => true, 'data' => $data], 200);
    }

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
