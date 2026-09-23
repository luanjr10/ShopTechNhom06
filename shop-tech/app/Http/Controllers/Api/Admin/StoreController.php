<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

/**
 * Admin xem toàn bộ gian hàng và bật/tắt trạng thái.
 */
class StoreController extends Controller
{
    // [GET] /api/admin/stores
    public function index(Request $request)
    {
        $query = Store::with('sellerProfile.user:id,name,username,email')
            ->withCount('products')
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [PATCH] /api/admin/stores/{store}/status
    public function updateStatus(Request $request, Store $store)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $store->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái gian hàng thành công',
            'data' => $store,
        ], 200);
    }
}
