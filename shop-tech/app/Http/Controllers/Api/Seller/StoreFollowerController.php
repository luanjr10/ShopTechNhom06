<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreFollow;
use Illuminate\Http\Request;

class StoreFollowerController extends Controller
{
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
