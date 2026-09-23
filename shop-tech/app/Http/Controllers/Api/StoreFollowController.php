<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreFollow;
use App\Services\RatingService;
use Illuminate\Http\Request;

class StoreFollowController extends Controller
{
    public function __construct(private RatingService $ratingService) {}

    // [POST] /api/stores/{slug}/follow — bấm lại để bỏ theo dõi (toggle).
    public function toggle(Request $request, string $slug)
    {
        $store = Store::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $user = $request->user();

        $existing = StoreFollow::where('user_id', $user->id)->where('store_id', $store->id)->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            StoreFollow::create(['user_id' => $user->id, 'store_id' => $store->id]);
            $following = true;
        }

        return response()->json([
            'success' => true,
            'message' => $following ? 'Đã theo dõi gian hàng' : 'Đã bỏ theo dõi',
            'data' => [
                'following' => $following,
                'followers_count' => $this->ratingService->followersCount($store->id),
            ],
        ], 200);
    }
}
