<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\CommissionService;
use App\Services\CouponService;
use Illuminate\Http\Request;
use RuntimeException;

class CouponController extends Controller
{
    public function __construct(
        private CouponService $couponService,
        private CommissionService $commissionService,
    ) {}

    public function apply(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
            'shipping_fee' => 'nullable|numeric|min:0',
            'receiver_phone' => 'nullable|string|max:20',
            'store_shipping_fees' => 'nullable|array',
            'store_shipping_fees.*' => 'numeric|min:0',
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->first();

        $storeSubtotals = $cart ? $this->commissionService->storeSubtotalsForCart($cart) : [];
        $storeShippingFees = $this->storeShippingFees(
            $validated['store_shipping_fees'] ?? null,
            $storeSubtotals,
            (float) ($validated['shipping_fee'] ?? 0),
        );

        try {
            $result = $this->couponService->apply(
                $validated['code'],
                (float) $validated['subtotal'],
                $user,
                (float) ($validated['shipping_fee'] ?? 0),
                $cart ? $this->commissionService->estimateForCart($cart) : null,
                $validated['receiver_phone'] ?? null,
                $storeSubtotals,
                $storeShippingFees,
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
                'capped' => $result['capped'],
                'store_funded' => $result['coupon']->store_id !== null,
                'original_discount' => $result['original_discount'],
            ],
        ], 200);
    }

    /**
     * Phí ship theo từng gian hàng: ưu tiên số liệu client gửi lên từ báo giá vận chuyển,
     * nếu thiếu thì chia tổng phí ship theo tỉ lệ giá trị hàng của từng gian hàng.
     *
     * @param  array<int|string, mixed>|null  $given
     * @param  array<int, float>  $storeSubtotals
     * @return array<int, float>
     */
    private function storeShippingFees(?array $given, array $storeSubtotals, float $totalShippingFee): array
    {
        if ($given) {
            return collect($given)->mapWithKeys(fn ($fee, $storeId) => [(int) $storeId => (float) $fee])->all();
        }

        $productsTotal = array_sum($storeSubtotals);

        if ($productsTotal <= 0) {
            return [];
        }

        return array_map(fn (float $subtotal) => round($totalShippingFee * $subtotal / $productsTotal, 2), $storeSubtotals);
    }
}
