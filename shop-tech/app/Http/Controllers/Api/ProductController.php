<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\NormalizesProductCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductUseCase;
use App\Models\UseCase;
use App\Services\CloudinaryService;
use App\Services\RatingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProductController extends Controller
{
    use NormalizesProductCatalog;

    private const PER_PAGE = 6;

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'newest');
        $categoryId = $request->input('category_id', $request->input('categoryId'));
        $brandId = $request->input('brand_id');
        $storeId = $request->input('store_id', $request->input('storeId'));
        $provinceId = $request->input('province_id');
        $useCase = $request->input('useCase', $request->input('use_case'));
        $useCaseId = $request->input('useCaseId', $request->input('use_case_id'));
        $isFeatured = $request->input('is_featured');
        $isFlashSale = $request->input('is_flash_sale');

        $query = Product::query()->with('store:id,name,slug,logo');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', (int) $categoryId);
        }

        if ($brandId !== null && $brandId !== '') {
            $query->where('brand_id', (int) $brandId);
        }

        if ($storeId !== null && $storeId !== '') {
            $query->where('store_id', (int) $storeId);
        }

        if ($provinceId !== null && $provinceId !== '') {
            $query->whereHas('store', fn ($storeQuery) => $storeQuery->where('province_id', (int) $provinceId));
        }

        if ($isFeatured !== null && $isFeatured !== '') {
            $query->where('is_featured', filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN));
        }

        if ($isFlashSale !== null && $isFlashSale !== '') {
            $query->where('is_flash_sale', filter_var($isFlashSale, FILTER_VALIDATE_BOOLEAN));
        }

        if (($useCase !== null && $useCase !== '') || ($useCaseId !== null && $useCaseId !== '')) {
            $productIds = $this->productIdsForUseCase($useCase, $useCaseId, $categoryId);
            $query->whereIn('id', $productIds);
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
                'final_price' => $this->calculateFinalPrice($product->price, $product->discount_percent),
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

    private function productIdsForUseCase(?string $useCaseSlug, ?string $useCaseId, $categoryId): array
    {
        $useCaseQuery = UseCase::query();

        if ($useCaseId !== null && $useCaseId !== '') {
            $useCaseQuery->where('_id', $useCaseId);
        } else {
            $useCaseQuery->where('slug', $useCaseSlug);
        }

        if ($categoryId !== null && $categoryId !== '') {
            $useCaseQuery->where('categoryId', (int) $categoryId);
        }

        $useCase = $useCaseQuery->first();

        if (! $useCase) {
            return [];
        }

        return ProductUseCase::where('useCaseIds', (string) $useCase->id)
            ->get()
            ->pluck('productId')
            ->all();
    }

    public function getProductDetail($id, RatingService $ratingService)
    {
        try {
            $product = is_numeric($id)
                ? Product::findOrFail($id)
                : Product::where('slug', $id)->firstOrFail();

            $product->load('store:id,name,slug,logo');

            $productId = (int) $product->id;

            $productSpecification = ProductSpecification::where(
                'productId',
                $productId
            )->first();

            $productImage = ProductImage::where(
                'productId',
                $productId
            )->first();

            $images = $productImage?->images ?? [];

            $productUseCase = ProductUseCase::where('productId', $productId)->first();

            $variants = $this->resolveVariants(
                $productSpecification?->variants ?? [],
                $product
            );

            $ratingStats = $ratingService->productStats($productId);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu thành công',
                'data' => [
                    ...$product->toArray(),

                    'images' => $images,
                    'thumbnail' => $images[0] ?? null,
                    'final_price' => $this->calculateFinalPrice($product->price, $product->discount_percent),

                    'specifications' => $productSpecification?->specifications ?? [],
                    'variants' => $variants,
                    'use_case_ids' => $productUseCase?->useCaseIds ?? [],

                    'rating' => $ratingStats['average'],
                    'reviews_count' => $ratingStats['count'],
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);

        } catch (Throwable $error) {

            return response()->json([
                'success' => false,
                'message' => 'Lấy dữ liệu thất bại',
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function createProduct(StoreProductRequest $request, CloudinaryService $cloudinaryService)
    {
        try {
            $imageUrls = $cloudinaryService->uploadMultipleImages(
                $request->file('images')
            );

            $product = Product::create([
                'code' => $request->code,
                'name' => $request->name,
                'slug' => Str::slug($request->name.'-'.$request->code),
                'price' => $request->price,
                'discount_percent' => $request->input('discount_percent', 0),
                'stock' => $request->stock,
                'status' => $request->status,
                'category_id' => $request->category_id,
            ]);

            ProductImage::updateOrCreate(
                [
                    'productId' => $product->id,
                ],
                [
                    'images' => $imageUrls,
                ]
            );

            $specifications = json_decode(
                $request->input('specifications', '[]'),
                true
            );

            $variants = $this->normalizeVariants(
                json_decode($request->input('variants', '[]'), true) ?: [],
                $product
            );

            ProductSpecification::updateOrCreate(
                [
                    'productId' => $product->id,
                ],
                [
                    'specifications' => $specifications,
                    'variants' => $variants,
                ]
            );

            $this->syncUseCases(
                $product->id,
                json_decode($request->input('use_case_ids', '[]'), true) ?: [],
                (int) $product->category_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data' => [
                    ...$product->toArray(),
                    'images' => $imageUrls,
                    'thumbnail' => $imageUrls[0] ?? null,
                    'final_price' => $this->calculateFinalPrice($product->price, $product->discount_percent),
                ],
            ], 201);

        } catch (Throwable $e) {

            Log::error('Lỗi tạo sản phẩm', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Tạo sản phẩm thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function updateProduct(UpdateProductRequest $request, $id, CloudinaryService $cloudinaryService)
    {
        try {
            $product = Product::findOrFail($id);

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

            ProductImage::updateOrCreate(
                [
                    'productId' => $product->id,
                ],
                [
                    'images' => $images,
                ]
            );

            $specifications = json_decode(
                $request->input('specifications', '[]'),
                true
            );

            $variants = $this->normalizeVariants(
                json_decode($request->input('variants', '[]'), true) ?: [],
                $product
            );

            ProductSpecification::updateOrCreate(
                [
                    'productId' => $product->id,
                ],
                [
                    'specifications' => $specifications,
                    'variants' => $variants,
                ]
            );

            $this->syncUseCases(
                $product->id,
                json_decode($request->input('use_case_ids', '[]'), true) ?: [],
                (int) $product->category_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công',
                'data' => [
                    ...$product->refresh()->toArray(),
                    'images' => $images,
                    'thumbnail' => $images[0] ?? null,
                    'final_price' => $this->calculateFinalPrice($product->price, $product->discount_percent),
                    'specifications' => $specifications,
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);

        } catch (Throwable $e) {

            Log::error('Lỗi cập nhật sản phẩm', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Cập nhật sản phẩm thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteProduct($id)
    {
        try {
            $product = Product::findOrFail($id);

            ProductImage::where('productId', (int) $id)->delete();
            ProductSpecification::where('productId', (int) $id)->delete();
            ProductUseCase::where('productId', (int) $id)->delete();
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công',
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);

        } catch (Throwable $e) {

            Log::error('Lỗi xóa sản phẩm', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Xóa sản phẩm thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
