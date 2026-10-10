<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Store;
use App\Services\CustomerTierService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Voucher do từng gian hàng phát hành: chỉ áp dụng cho sản phẩm của gian hàng và khoản giảm do gian hàng chịu.
 */
class CouponController extends Controller
{
    public function __construct(private CustomerTierService $tierService) {}

    public function index(Request $request, Store $store)
    {
        $query = Coupon::query()
            ->where('store_id', $store->id)
            ->whereNull('trade_in_request_id')
            ->withCount(['claims', 'redemptions'])
            ->latest();

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

    public function store(Request $request, Store $store)
    {
        $coupon = Coupon::create($this->validated($request) + ['store_id' => $store->id]);

        return response()->json(['success' => true, 'message' => 'Đã tạo voucher', 'data' => $coupon], 201);
    }

    public function update(Request $request, Store $store, Coupon $coupon)
    {
        $this->assertOwnedByStore($coupon, $store);

        $coupon->update($this->validated($request, $coupon));

        return response()->json(['success' => true, 'message' => 'Đã cập nhật voucher', 'data' => $coupon], 200);
    }

    public function destroy(Store $store, Coupon $coupon)
    {
        $this->assertOwnedByStore($coupon, $store);

        $coupon->delete();

        return response()->json(['success' => true, 'message' => 'Đã xoá voucher'], 200);
    }

    private function assertOwnedByStore(Coupon $coupon, Store $store): void
    {
        abort_unless($coupon->store_id === $store->id && $coupon->trade_in_request_id === null, 404, 'Không tìm thấy voucher');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $tierKeys = array_column($this->tierService->all(), 'key');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(['percent', 'fixed', 'free_ship'])],
            'target_tier' => ['nullable', Rule::in($tierKeys)],
            'new_customer_only' => ['boolean'],
            'weekday' => ['nullable', 'integer', 'between:0,6'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
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
        $validated['new_customer_only'] = $validated['new_customer_only'] ?? false;

        return $validated;
    }
}
