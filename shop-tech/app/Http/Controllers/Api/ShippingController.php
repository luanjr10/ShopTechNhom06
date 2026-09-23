<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use RuntimeException;

class ShippingController extends Controller
{
    public function __construct(private ShippingService $shippingService) {}

    /**
     * [POST] /api/shipping/fee — tính phí vận chuyển THẬT qua GHN cho giỏ hàng.
     * KHÔNG có fallback 0đ: lỗi (thiếu weight/dimension, store chưa có địa chỉ,
     * GHN lỗi...) trả 422 kèm message rõ ràng, FE PHẢI chặn thanh toán khi gặp lỗi này.
     */
    public function fee(Request $request)
    {
        $validated = $request->validate([
            'district_id' => 'required|integer',
            'ward_code' => 'required|string',
            'province_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $quote = $this->shippingService->quoteCart(
                $validated['items'],
                (int) $validated['district_id'],
                (string) $validated['ward_code'],
                isset($validated['province_id']) ? (int) $validated['province_id'] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $quote], 200);
    }
}
