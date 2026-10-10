<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\Store;
use App\Rules\VietnamesePhone;
use App\Services\AffiliateService;
use Illuminate\Http\Request;
use RuntimeException;

class AffiliateController extends Controller
{
    public function __construct(private AffiliateService $affiliateService) {}

    public function products(Request $request)
    {
        $paginator = $this->affiliateService->catalog($request->only(['search', 'store_id', 'per_page']));

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $stores = $this->affiliateService->storesFor($user);

        $totals = ['available' => 0.0, 'pending' => 0.0, 'withdrawing' => 0.0, 'withdrawn' => 0.0, 'total_earned' => 0.0];
        foreach ($stores as $entry) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $entry['balances'][$key];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $this->affiliateService->ensureReferralCode($user),
                'totals' => $totals,
                'stores' => $stores,
            ],
        ], 200);
    }

    public function commissions(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => AffiliateCommission::where('referrer_id', $request->user()->id)
                ->with(['referredUser:id,name', 'store:id,name'])
                ->latest()
                ->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    public function withdrawals(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => AffiliateWithdrawal::where('user_id', $request->user()->id)
                ->with('store:id,name')
                ->latest()
                ->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    public function requestWithdrawal(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'amount' => 'required|numeric|min:1',
            'momo_phone' => ['required', 'string', new VietnamesePhone],
            'account_holder' => 'required|string|max:100',
        ]);

        try {
            $withdrawal = $this->affiliateService->requestWithdrawal(
                $request->user(),
                Store::findOrFail($validated['store_id']),
                $validated,
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu rút tiền tới gian hàng',
            'data' => $withdrawal,
        ], 201);
    }
}
