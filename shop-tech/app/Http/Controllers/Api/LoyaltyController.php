<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomerTierService;
use Illuminate\Http\Request;

/**
 * Hạng thành viên của khách đang đăng nhập — hiển thị ở "Tài khoản của tôi".
 */
class LoyaltyController extends Controller
{
    public function __construct(private CustomerTierService $tierService) {}

    // [GET] /api/loyalty/summary
    public function summary(Request $request)
    {
        $user = $request->user();
        $totalSpent = $this->tierService->totalSpentForUser($user->id);
        $tier = $this->tierService->resolve($totalSpent);

        return response()->json([
            'success' => true,
            'data' => [
                'tier' => $tier,
                'tier_label' => $this->tierService->label($tier),
                'tier_color' => $this->tierService->color($tier),
                'total_spent' => $totalSpent,
                'next_tier' => $this->tierService->nextTier($totalSpent),
                'tiers' => $this->tierService->all(),
            ],
        ], 200);
    }
}
