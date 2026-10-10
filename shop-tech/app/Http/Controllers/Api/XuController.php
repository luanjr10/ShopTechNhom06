<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\XuTransaction;
use App\Services\CommissionService;
use App\Services\XuService;
use Illuminate\Http\Request;
use RuntimeException;

class XuController extends Controller
{
    public function __construct(
        private XuService $xuService,
        private CommissionService $commissionService,
    ) {}

    public function summary(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->xuService->summary($request->user())], 200);
    }

    public function checkIn(Request $request)
    {
        try {
            $result = $this->xuService->checkIn($request->user());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['bonus']
                ? "Điểm danh thành công! Chuỗi {$result['streak']} ngày, nhận {$result['xu']} xu (đã gồm thưởng chuỗi)."
                : "Điểm danh thành công! Nhận {$result['xu']} xu.",
            'data' => $result + ['summary' => $this->xuService->summary($request->user())],
        ], 200);
    }

    public function transactions(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => XuTransaction::where('user_id', $request->user()->id)
                ->latest('id')
                ->paginate((int) $request->input('per_page', 20)),
        ], 200);
    }

    /**
     * Xem trước số xu tối đa dùng được cho giỏ hàng hiện tại (dùng ở bước thanh toán).
     */
    public function redeemable(Request $request)
    {
        $validated = $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_is_free_ship' => 'nullable|boolean',
            'discount_is_store_funded' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->first();
        $discount = (float) ($validated['discount_amount'] ?? 0);
        $productDiscount = ($validated['discount_is_free_ship'] ?? false) ? 0.0 : $discount;
        $platformDiscount = ($validated['discount_is_store_funded'] ?? false) ? 0.0 : $discount;
        $commission = $cart ? $this->commissionService->estimateForCart($cart) : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $this->xuService->summary($user)['balance'],
                'max_usable' => $this->xuService->maxRedeemable(
                    $user,
                    (float) $validated['subtotal'] - $productDiscount,
                    $commission - $platformDiscount,
                ),
            ],
        ], 200);
    }
}
