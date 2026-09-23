<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kho hàng: xem tồn kho + điều chỉnh số lượng (kèm log lịch sử) cho sản phẩm
 * trong một gian hàng cụ thể. store_id luôn ép theo route {store} (đã qua store.owner).
 */
class InventoryController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 10;

    // [GET] /api/seller/stores/{store}/inventory
    public function index(Request $request, Store $store)
    {
        $query = Product::where('store_id', $store->id);

        if ($request->boolean('low_stock')) {
            $query->where('stock', '<=', self::LOW_STOCK_THRESHOLD);
        }

        $paginator = $query->orderBy('stock')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'stock' => $p->stock,
                'low_stock' => $p->stock <= self::LOW_STOCK_THRESHOLD,
                'status' => $p->status,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
            ],
        ], 200);
    }

    // [POST] /api/seller/stores/{store}/products/{product}/stock-adjustments
    public function adjust(Request $request, Store $store, Product $product)
    {
        if ($product->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Sản phẩm không thuộc gian hàng này'], 404);
        }

        $validated = $request->validate([
            // change: số dương (nhập thêm) hoặc âm (xuất/hao hụt).
            'change' => 'required|integer|not_in:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $newStock = $product->stock + $validated['change'];
        if ($newStock < 0) {
            return response()->json(['success' => false, 'message' => 'Số lượng tồn kho không thể âm'], 422);
        }

        $adjustment = DB::transaction(function () use ($request, $store, $product, $validated, $newStock) {
            $previousStock = $product->stock;
            $product->update(['stock' => $newStock]);

            return StockAdjustment::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'seller_profile_id' => $request->user()->sellerProfile->id,
                'previous_stock' => $previousStock,
                'change' => $validated['change'],
                'new_stock' => $newStock,
                'reason' => $validated['reason'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật tồn kho thành công',
            'data' => $adjustment,
        ], 200);
    }

    // [GET] /api/seller/stores/{store}/products/{product}/stock-adjustments
    public function history(Store $store, Product $product)
    {
        if ($product->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Sản phẩm không thuộc gian hàng này'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => StockAdjustment::where('product_id', $product->id)->latest()->get(),
        ], 200);
    }
}
