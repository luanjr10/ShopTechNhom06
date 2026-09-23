<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

/**
 * Quản lý "Nổi bật trang chủ": giờ kết thúc flash sale (đếm ngược) + đánh dấu
 * sản phẩm nào thuộc Flash sale / Sản phẩm hot trend cho storefront hiển thị.
 * Xem routes/api/admin.php (module `home_highlights`).
 */
class HomeHighlightController extends Controller
{
    private const PER_PAGE = 10;

    // [GET] /api/admin/home-highlights/flash-sale
    public function showFlashSale()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ends_at' => SiteSetting::get('flash_sale_ends_at'),
            ],
        ], 200);
    }

    // [PUT] /api/admin/home-highlights/flash-sale
    public function updateFlashSale(Request $request)
    {
        $validated = $request->validate([
            'ends_at' => 'nullable|date',
        ]);

        SiteSetting::set('flash_sale_ends_at', $validated['ends_at'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật thời gian kết thúc flash sale',
            'data' => ['ends_at' => $validated['ends_at'] ?? null],
        ], 200);
    }

    // [GET] /api/admin/home-highlights/products — danh sách sản phẩm để bật/tắt Flash sale + Hot trend.
    public function products(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $highlight = $request->input('highlight'); // 'featured' | 'flash_sale' | null

        $query = Product::query()->with('brand:id,name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($highlight === 'featured') {
            $query->where('is_featured', true);
        } elseif ($highlight === 'flash_sale') {
            $query->where('is_flash_sale', true);
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', self::PER_PAGE), 100);
        $paginator = $query->paginate($perPage ?: self::PER_PAGE);

        $imagesByProductId = ProductImage::whereIn(
            'productId',
            collect($paginator->items())->pluck('id')->all()
        )->get()->keyBy('productId');

        $data = collect($paginator->items())->map(function (Product $product) use ($imagesByProductId) {
            $images = $imagesByProductId->get($product->id)?->images ?? [];

            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'thumbnail' => $images[0] ?? null,
                'price' => $product->price,
                'discount_percent' => $product->discount_percent,
                'is_featured' => (bool) $product->is_featured,
                'is_flash_sale' => (bool) $product->is_flash_sale,
                'brand' => $product->brand?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    // [PATCH] /api/admin/home-highlights/products/{id}
    public function updateProductFlags(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'is_featured' => 'sometimes|boolean',
            'is_flash_sale' => 'sometimes|boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật',
            'data' => [
                'id' => $product->id,
                'is_featured' => (bool) $product->is_featured,
                'is_flash_sale' => (bool) $product->is_flash_sale,
            ],
        ], 200);
    }
}
