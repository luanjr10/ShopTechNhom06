<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use Illuminate\Http\Request;

/**
 * Admin cấu hình hoa hồng: mặc định + mở rộng theo Category/Store.
 * KHÔNG ảnh hưởng đơn cũ (đơn đã snapshot commission_rate lúc tạo).
 */
class CommissionController extends Controller
{
    // [GET] /api/admin/commissions
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => CommissionSetting::with(['category:id,name', 'store:id,name'])
                ->orderBy('scope')
                ->get(),
        ], 200);
    }

    // [POST] /api/admin/commissions — tạo/cập nhật theo scope (default 1 dòng duy nhất).
    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'scope' => 'required|in:default,category,store',
            'category_id' => 'required_if:scope,category|nullable|integer|exists:categories,id',
            'store_id' => 'required_if:scope,store|nullable|integer|exists:stores,id',
            'rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $match = match ($validated['scope']) {
            'default' => ['scope' => 'default'],
            'category' => ['scope' => 'category', 'category_id' => $validated['category_id']],
            'store' => ['scope' => 'store', 'store_id' => $validated['store_id']],
        };

        $setting = CommissionSetting::updateOrCreate($match, [
            'rate' => $validated['rate'],
            'is_active' => $validated['is_active'] ?? true,
            'category_id' => $validated['category_id'] ?? null,
            'store_id' => $validated['store_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lưu cấu hình hoa hồng thành công',
            'data' => $setting,
        ], 200);
    }

    // [DELETE] /api/admin/commissions/{commission}
    public function destroy(CommissionSetting $commission)
    {
        if ($commission->scope === 'default') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa cấu hình mặc định',
            ], 422);
        }

        $commission->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa cấu hình hoa hồng'], 200);
    }
}
