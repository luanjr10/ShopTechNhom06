<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreFollow;
use Illuminate\Http\Request;

/**
 * Seller xem danh sách khách đang theo dõi gian hàng của mình — chỉ xem.
 */
class StoreFollowerController extends Controller
{
    // [GET] /api/seller/stores/{store}/followers
    public function index(Request $request, Store $store)
    {
        $query = StoreFollow::where('store_id', $store->id)
            ->with('user:id,name,email,phone,avatar,google_avatar')
            ->latest();

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }
}
