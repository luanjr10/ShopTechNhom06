<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Concerns\NormalizesProductCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductUseCase;
use App\Models\Store;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Seller quản lý sản phẩm TRONG một gian hàng cụ thể. store_id luôn ép theo route
 * {store} (đã qua middleware store.owner). Tái dùng CloudinaryService + các model Mongo
 * (ProductImage/ProductSpecification) giống ProductController; phần đọc chi tiết + variant
 * mặc định vẫn do ProductController@getProductDetail xử lý (route public, không cần bản
 * riêng cho seller). Chuẩn hoá variants/Quick Link dùng chung trait NormalizesProductCatalog
 * để cùng cấu trúc dữ liệu với catalog admin.
 */
class SellerProductController extends Controller
{
    use NormalizesProductCatalog;

    private const PER_PAGE = 15;

    // [GET] /api/seller/stores/{store}/products
    public function index(Request $request, Store $store)
    {
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'newest');

        $query = Product::where('store_id', $store->id);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'discount_desc' => $query->orderByDesc('discount_percent'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = min((int) $request->input('per_page', self::PER_PAGE), 100);
        $paginator = $query->paginate($perPage ?: self::PER_PAGE);

        $imagesByProductId = ProductImage::whereIn(
            'productId',
            collect($paginator->items())->pluck('id')->all()
        )->get()->keyBy('productId');

        $data = collect($paginator->items())->map(function (Product $product) use ($imagesByProductId) {
            $images = $imagesByProductId->get($product->id)?->images ?? [];

            return [
                ...$product->toArray(),
                'images' => $images,
                'thumbnail' => $images[0] ?? null,
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

    // [POST] /api/seller/stores/{store}/products
    public function store(SellerProductRequest $request, Store $store, CloudinaryService $cloudinaryService)
    {
        try {
            $imageUrls = $cloudinaryService->uploadMultipleImages($request->file('images'));

            $product = Product::create([
                'code' => $request->code,
                'name' => $request->name,
                'slug' => Str::slug($request->name.'-'.$request->code),
                'price' => $request->price,
                'discount_percent' => $request->input('discount_percent', 0),
                'stock' => $request->stock,
                'status' => $request->status,
                'category_id' => $request->category_id,
                'store_id' => $store->id, // ép theo gian hàng đang chọn
            ]);

            $this->syncMongo($product, $imageUrls, $request);

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data' => [...$product->toArray(), 'images' => $imageUrls, 'thumbnail' => $imageUrls[0] ?? null],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo sản phẩm thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // [POST|PATCH] /api/seller/stores/{store}/products/{product}
    public function update(SellerProductRequest $request, Store $store, Product $product, CloudinaryService $cloudinaryService)
    {
        if ($product->store_id !== $store->id) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không thuộc gian hàng này',
            ], 404);
        }

        try {
            $keptImages = json_decode($request->input('existing_images', '[]'), true) ?: [];
            $newImageUrls = $request->hasFile('images')
                ? $cloudinaryService->uploadMultipleImages($request->file('images'))
                : [];
            $images = [...$keptImages, ...$newImageUrls];

            if (empty($images)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sản phẩm phải có ít nhất một ảnh',
                ], 422);
            }

            $product->update([
                'name' => $request->name,
                'price' => $request->price,
                'discount_percent' => $request->input('discount_percent', 0),
                'stock' => $request->stock,
                'status' => $request->status,
                'category_id' => $request->category_id,
            ]);

            $this->syncMongo($product, $images, $request);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công',
                'data' => [...$product->refresh()->toArray(), 'images' => $images, 'thumbnail' => $images[0] ?? null],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật sản phẩm thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // [DELETE] /api/seller/stores/{store}/products/{product}
    public function destroy(Store $store, Product $product)
    {
        if ($product->store_id !== $store->id) {
            return response()->json(['success' => false, 'message' => 'Sản phẩm không thuộc gian hàng này'], 404);
        }

        ProductImage::where('productId', (int) $product->id)->delete();
        ProductSpecification::where('productId', (int) $product->id)->delete();
        ProductUseCase::where('productId', (int) $product->id)->delete();
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Xóa sản phẩm thành công'], 200);
    }

    /**
     * Lưu ảnh + specifications + variants (chuẩn hoá) + Quick Link sang MongoDB,
     * cùng cấu trúc dữ liệu với ProductController (admin) qua trait dùng chung.
     */
    private function syncMongo(Product $product, array $images, SellerProductRequest $request): void
    {
        ProductImage::updateOrCreate(['productId' => $product->id], ['images' => $images]);

        $variants = $this->normalizeVariants(
            json_decode($request->input('variants', '[]'), true) ?: [],
            $product
        );

        ProductSpecification::updateOrCreate(
            ['productId' => $product->id],
            [
                'specifications' => json_decode($request->input('specifications', '[]'), true) ?: [],
                'variants' => $variants,
            ],
        );

        $this->syncUseCases(
            $product->id,
            json_decode($request->input('use_case_ids', '[]'), true) ?: [],
            (int) $product->category_id
        );
    }
}
