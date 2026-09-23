<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Models\Brands;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BrandController extends Controller
{
    private const PER_PAGE = 6;

    // [GET] /api/brands
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'newest');
        $query = Brands::query();
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        match ($sort) {
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = min((int) $request->input('per_page', self::PER_PAGE), 100);
        $paginator = $query->paginate($perPage ?: self::PER_PAGE);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    // [GET] /api/brands // No pagination
    public function getAll()
    {
        $brands = Brands::query()->get();

        return response()->json([
            'success' => true,
            'data' => $brands,
        ], 200);
    }

    // [GET] /api/brands/id
    public function getBrandDetail($id)
    {
        try {
            // Lấy sản phẩm từ MySQL
            $brand = Brands::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu thành công',
                'data' => $brand,
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thương hiệu',
            ], 404);

        } catch (Throwable $error) {

            return response()->json([
                'success' => false,
                'message' => 'Lấy dữ liệu thất bại',
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    // [POST] /api/brands
    public function createBrand(StoreBrandRequest $request, CloudinaryService $cloudinaryService)
    {
        try {
            // Upload toàn bộ ảnh lên Cloudinary
            $imageUrls = $cloudinaryService->uploadMultipleImages(
                $request->file('images')
            );

            $brand = Brands::create([
                'code' => $request->code,
                'name' => $request->name,
                'logo' => $imageUrls[0] ?? null,
                'description' => $request->description,
                'slug' => Str::slug($request->name.'-'.$request->code),
                'status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo Thương Hiệu Mới thành công',
                'data' => [
                    ...$brand->toArray(),
                    'images' => $imageUrls,
                ],
            ], 201);

        } catch (Throwable $e) {

            // Ghi lỗi vào storage/logs/laravel.log
            Log::error('Lỗi tạo thương hiệu', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Tạo thương hiệu thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    // [PATCH] /api/brands
    public function updateBrand(UpdateBrandRequest $request, $id, CloudinaryService $cloudinaryService)
    {
        try {
            $brand = Brands::findOrFail($id);

            // Lấy ảnh hiện tại được giữ lại
            $keptImages = json_decode(
                $request->input('existing_images', '[]'),
                true
            ) ?: [];

            // Nếu có ảnh mới thì upload
            $newImageUrls = [];

            if ($request->hasFile('images')) {
                $newImageUrls = $cloudinaryService->uploadMultipleImages(
                    $request->file('images')
                );
            }

            /*
            * Brand chỉ có 1 logo:
            *
            * Có ảnh mới -> dùng ảnh mới
            * Không có ảnh mới -> giữ ảnh cũ
            */
            $logo = $newImageUrls[0]
                ?? ($keptImages[0] ?? null);

            // Không có ảnh
            if (! $logo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thương hiệu phải có một ảnh',
                ], 422);
            }

            $brand->update([
                'name' => $request->name,
                'slug' => 'brand-'.Str::slug($request->name),
                'logo' => $logo,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thương hiệu thành công',
                'data' => [
                    ...$brand->refresh()->toArray(),
                    'images' => [$logo],
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thương hiệu',
            ], 404);

        } catch (Throwable $e) {

            Log::error('Lỗi cập nhật thương hiệu', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cập nhật thương hiệu thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // [DELETE] /api/brands/id
    public function deleteBrand($id)
    {
        try {
            $brand = Brands::findOrFail($id);

            $brand->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa thương hiệu thành công',
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thương hiệu',
            ], 404);

        } catch (Throwable $e) {

            Log::error('Lỗi xóa thương hiệu', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Xóa thương hiệu thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
