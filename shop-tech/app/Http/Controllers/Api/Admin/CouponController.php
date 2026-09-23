<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\CustomerTierService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin tạo/quản lý voucher — bao gồm voucher hạng thành viên (target_tier),
 * VD: khách đã mua trên 10 triệu (hạng Bạc trở lên) được tạo voucher áp dụng
 * cho toàn bộ khách hạng đó, trên MỌI gian hàng (voucher do sàn chịu, xem
 * OrderService::place — không trừ vào tiền seller nhận).
 */
class CouponController extends Controller
{
    public function __construct(private CustomerTierService $tierService) {}

    // [GET] /api/admin/coupons
    public function index(Request $request)
    {
        $query = Coupon::query()->withCount(['claims', 'redemptions'])->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
            'tiers' => $this->tierService->all(),
        ], 200);
    }

    // [POST] /api/admin/coupons
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $coupon = Coupon::create($validated);

        return response()->json(['success' => true, 'message' => 'Đã tạo voucher', 'data' => $coupon], 201);
    }

    // [PATCH] /api/admin/coupons/{coupon}
    public function update(Request $request, Coupon $coupon)
    {
        $validated = $this->validated($request, $coupon);

        $coupon->update($validated);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật voucher', 'data' => $coupon], 200);
    }

    // [DELETE] /api/admin/coupons/{coupon}
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json(['success' => true, 'message' => 'Đã xoá voucher'], 200);
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $tierKeys = array_column($this->tierService->all(), 'key');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(['percent', 'fixed', 'free_ship'])],
            'target_tier' => ['nullable', Rule::in($tierKeys)],
            'value' => ['required_unless:type,free_ship', 'nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = mb_strtoupper(trim($validated['code']));
        $validated['is_free_ship'] = $validated['type'] === 'free_ship';
        $validated['value'] = $validated['value'] ?? 0;
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        return $validated;
    }
}
