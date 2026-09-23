<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\Request;
use RuntimeException;

class CouponController extends Controller
{
    public function __construct(private CouponService $couponService) {}

    // [POST] /api/coupons/apply — xem trước số tiền giảm (không lưu gì, chỉ để hiển thị ở checkout).
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
            'shipping_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->couponService->apply(
                $validated['code'],
                (float) $validated['subtotal'],
                $request->user(),
                (float) ($validated['shipping_fee'] ?? 0),
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $result['coupon']->code,
                'discount_amount' => $result['discount_amount'],
                'discount_target' => $result['discount_target'],
                'is_free_ship' => (bool) $result['coupon']->is_free_ship,
            ],
        ], 200);
    }
}
