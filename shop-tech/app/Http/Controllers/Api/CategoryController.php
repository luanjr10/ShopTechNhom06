<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\CategoryImage;
use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class CategoryController extends Controller
{
    private const PER_PAGE = 6;

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'newest');
        $parentId = $request->input('parent_id');

        $query = Category::query()->withCount(['products', 'children'])->with('parent:id,name');

        if ($request->boolean('with_brands')) {
            $query->with('brands:id,code,name,logo');
        }

        if ($request->boolean('with_children')) {
            $query->with(['children' => function ($q) {
                $q->where('status', 1)->orderBy('status_order')
                    ->withCount('children')
                    ->with(['children' => fn ($q2) => $q2->where('status', 1)->orderBy('status_order')->withCount('children')]);
            }]);
        }

        if ($parentId !== null) {
            $query->where('parent_id', in_array($parentId, ['null', 'top_level', '0'], true) ? null : (int) $parentId);
        }

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

        $data = $this->attachImages(collect($paginator->items()));

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

    public function getCategoryById($id)
    {
        try {
            $category = Category::with(['brands:id', 'parent:id,name', 'children:id,parent_id,name,slug,icon'])
                ->withCount('children')
                ->findOrFail($id);

            $data = $this->attachImages(collect([$category]))[0];
            $data['brand_ids'] = $category->brands->pluck('id')->all();
            unset($data['brands']);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }
    }

    private function attachImages(Collection $categories): array
    {
        $ids = collect();
        $collectIds = function ($cats) use (&$collectIds, &$ids) {
            foreach ($cats as $cat) {
                $ids->push($cat->id);
                if ($cat->relationLoaded('children')) {
                    $collectIds($cat->children);
                }
            }
        };
        $collectIds($categories);

        $imagesByCategoryId = CategoryImage::whereIn('categoryId', $ids->unique()->all())
            ->get()
            ->keyBy('categoryId');

        $mapCategory = function (Category $cat) use (&$mapCategory, $imagesByCategoryId) {
            $arr = $cat->toArray();
            $arr['image'] = $imagesByCategoryId->get($cat->id)?->image;

            if ($cat->relationLoaded('children')) {
                $arr['children'] = $cat->children->map($mapCategory)->values()->all();
            }

            return $arr;
        };

        return $categories->map($mapCategory)->all();
    }

    public function uploadImage(Request $request, $id, CloudinaryService $cloudinaryService)
    {
        try {
            $category = Category::findOrFail($id);

            $request->validate([
                'image' => 'required|image|max:5120',
            ], [
                'image.required' => 'Vui lòng chọn ảnh',
                'image.image' => 'Tệp phải là hình ảnh',
                'image.max' => 'Ảnh không được vượt quá 5MB',
            ]);

            $url = $cloudinaryService->uploadImage($request->file('image'), 'category-images');

            CategoryImage::updateOrCreate(
                ['categoryId' => $category->id],
                ['image' => $url]
            );

            $category->update(['display_type' => 'image']);

            return response()->json([
                'success' => true,
                'message' => 'Tải ảnh danh mục thành công',
                'data' => ['image' => $url],
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);

        } catch (Throwable $error) {

            return response()->json([
                'success' => false,
                'message' => 'Tải ảnh danh mục thất bại',
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function deleteImage($id)
    {
        try {
            $category = Category::findOrFail($id);

            CategoryImage::where('categoryId', $category->id)->delete();
            $category->update(['display_type' => 'icon']);

            return response()->json([
                'success' => true,
                'message' => 'Đã gỡ ảnh danh mục, quay lại hiển thị bằng icon',
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create([
            'parent_id' => $request->input('parent_id'),
            'code' => $request->code,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'icon' => $request->icon,
            'color' => $request->color,
            'display_type' => $request->input('display_type', 'icon'),
            'status' => $request->status,
        ]);

        $category->brands()->sync($request->input('brand_ids', []));

        return response()->json([
            'success' => true,
            'message' => 'Thêm Mới Danh Mục Thành Công',
            'data' => $category,
        ], 201);
    }

    public function editCategory(UpdateCategoryRequest $request, $id)
    {
        try {
            $category = Category::findOrFail($id);

            $category->update([
                'parent_id' => $request->input('parent_id'),
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'icon' => $request->icon,
                'color' => $request->color,
                'display_type' => $request->input('display_type', $category->display_type),
                'status' => $request->status,
            ]);

            $category->brands()->sync($request->input('brand_ids', []));

            return response()->json([
                'success' => true,
                'message' => 'Cập Nhật Danh Mục Thành Công',
                'data' => $category,
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);

        } catch (Throwable $error) {

            return response()->json([
                'success' => false,
                'message' => 'Cập Nhật Danh Mục Thất Bại',
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function deleteCategory($id)
    {
        try {
            $category = Category::findOrFail($id);

            if ($category->children()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Danh mục đang có danh mục con, vui lòng xóa các danh mục con trước',
                ], 409);
            }

            CategoryImage::where('categoryId', $category->id)->delete();
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa Danh Mục Thành Công',
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);

        } catch (Throwable $error) {

            return response()->json([
                'success' => false,
                'message' => 'Xóa Danh Mục Thất Bại',
                'error' => $error->getMessage(),
            ], 500);
        }
    }
}
