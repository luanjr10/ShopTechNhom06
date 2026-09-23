<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UseCase\StoreUseCaseRequest;
use App\Http\Requests\UseCase\UpdateUseCaseRequest;
use App\Models\Category;
use App\Models\UseCase;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UseCaseController extends Controller
{
    /**
     * [GET] /api/use-cases?categoryId=xxx
     * Danh sách Quick Link đang hiển thị của một danh mục (dùng cho storefront).
     */
    public function index(Request $request)
    {
        $categoryId = $request->input('categoryId', $request->input('category_id'));

        if ($categoryId === null || $categoryId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Thiếu categoryId',
            ], 422);
        }

        $useCases = UseCase::where('categoryId', (int) $categoryId)
            ->where('status', true)
            ->orderBy('sortOrder')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $useCases,
        ], 200);
    }

    /**
     * [GET] /api/categories/{categoryId}/use-cases
     * Danh sách Quick Link (mọi trạng thái) của một danh mục — dùng cho admin.
     */
    public function indexByCategory($categoryId)
    {
        try {
            Category::findOrFail($categoryId);

            $useCases = UseCase::where('categoryId', (int) $categoryId)
                ->orderBy('sortOrder')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $useCases,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }
    }

    /**
     * [POST] /api/categories/{categoryId}/use-cases
     * Tạo Quick Link cho danh mục hiện tại — categoryId lấy từ route, không cho nhập tay.
     */
    public function store(StoreUseCaseRequest $request, $categoryId, CloudinaryService $cloudinaryService)
    {
        try {
            Category::findOrFail($categoryId);

            $imageUrl = $cloudinaryService->uploadImage($request->file('image'));

            $useCase = UseCase::create([
                'categoryId' => (int) $categoryId,
                'name' => $request->name,
                'slug' => $this->uniqueSlug($request->name, (int) $categoryId),
                'image' => $imageUrl,
                'sortOrder' => (int) $request->input('sortOrder', 0),
                'status' => (bool) $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo Quick Link thành công',
                'data' => $useCase,
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);

        } catch (Throwable $e) {
            Log::error('Lỗi tạo Quick Link', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tạo Quick Link thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * [POST|PATCH] /api/categories/{categoryId}/use-cases/{useCaseId}
     * Cập nhật Quick Link — bắt buộc Quick Link phải thuộc đúng danh mục hiện tại.
     */
    public function update(UpdateUseCaseRequest $request, $categoryId, $useCaseId, CloudinaryService $cloudinaryService)
    {
        try {
            $useCase = $this->findInCategory($categoryId, $useCaseId);

            $data = [
                'name' => $request->name,
                'slug' => $this->uniqueSlug($request->name, (int) $categoryId, $useCase->id),
                'sortOrder' => (int) $request->input('sortOrder', $useCase->sortOrder ?? 0),
                'status' => (bool) $request->status,
            ];

            if ($request->hasFile('image')) {
                $data['image'] = $cloudinaryService->uploadImage($request->file('image'));
            }

            $useCase->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật Quick Link thành công',
                'data' => $useCase->refresh(),
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Quick Link trong danh mục này',
            ], 404);

        } catch (Throwable $e) {
            Log::error('Lỗi cập nhật Quick Link', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cập nhật Quick Link thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * [DELETE] /api/categories/{categoryId}/use-cases/{useCaseId}
     */
    public function destroy($categoryId, $useCaseId)
    {
        try {
            $useCase = $this->findInCategory($categoryId, $useCaseId);
            $useCase->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa Quick Link thành công',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy Quick Link trong danh mục này',
            ], 404);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa Quick Link thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tìm Quick Link và đảm bảo nó thuộc đúng danh mục đang thao tác.
     */
    private function findInCategory($categoryId, $useCaseId): UseCase
    {
        return UseCase::where('_id', $useCaseId)
            ->where('categoryId', (int) $categoryId)
            ->firstOrFail();
    }

    /**
     * Sinh slug duy nhất trong phạm vi một danh mục (bỏ qua chính bản ghi khi sửa).
     */
    private function uniqueSlug(string $name, int $categoryId, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            UseCase::where('categoryId', $categoryId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('_id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
