<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreFollow;
use Illuminate\Http\Request;

class StoreFollowController extends Controller
{
    public function index(Request $request)
    {
        $query = StoreFollow::with(['user:id,name,email', 'store:id,name,slug'])->latest();

        if ($storeId = $request->input('store_id')) {
            $query->where('store_id', (int) $storeId);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }
}
