<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\RatingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Xem thông tin gian hàng (public, chỉ store đang active) + thống kê người bán.
 */
class PublicStoreController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(private RatingService $ratingService) {}

    // [GET] /api/stores — danh sách gian hàng để khách khám phá ("Kênh người bán").
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $sort = $request->input('sort', 'newest');
        $provinceId = $request->input('province_id');

        $query = Store::where('status', 'active')
            ->withCount(['products', 'followers', 'reviews'])
            ->withAvg('reviews', 'rating');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        // Lọc theo tỉnh/thành khách đang chọn ở header (kho lấy hàng của gian hàng).
        if ($provinceId !== null && $provinceId !== '') {
            $query->where('province_id', (int) $provinceId);
        }

        match ($sort) {
            'products_desc' => $query->orderByDesc('products_count'),
            'followers_desc' => $query->orderByDesc('followers_count'),
            'name_asc' => $query->orderBy('name'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = min((int) $request->input('per_page', self::PER_PAGE), 48);
        $paginator = $query->paginate($perPage ?: self::PER_PAGE);

        $data = collect($paginator->items())->map(fn (Store $store) => [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'logo' => $store->logo,
            'description' => $store->description,
            'products_count' => $store->products_count,
            'followers_count' => $store->followers_count,
            'reviews_count' => $store->reviews_count,
            'rating' => $store->reviews_avg_rating ? round((float) $store->reviews_avg_rating, 1) : 0,
            'created_at' => $store->created_at,
        ]);

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

    // [GET] /api/stores/{slug}
    public function show(string $slug)
    {
        try {
            $store = Store::where('slug', $slug)
                ->where('status', 'active')
                ->withCount('products')
                ->with('sellerProfile:id,display_name,created_at')
                ->firstOrFail();

            $completed = SellerOrder::where('store_id', $store->id)
                ->where('status', 'completed')->count();
            $total = SellerOrder::where('store_id', $store->id)->count();
            $orders30d = SellerOrder::where('store_id', $store->id)
                ->where('created_at', '>=', now()->subDays(30))->count();

            $level = match (true) {
                $completed >= 100 => 'Pro Seller',
                $completed >= 20 => 'Trusted Seller',
                default => 'New Seller',
            };

            // Danh mục shop đang bán (từ sản phẩm thật).
            $categoryIds = Product::where('store_id', $store->id)
                ->distinct()->pluck('category_id')->filter()->all();
            $categories = Category::whereIn('id', $categoryIds)
                ->get(['id', 'name', 'slug']);

            $ratingStats = $this->ratingService->storeStats($store->id);

            return response()->json([
                'success' => true,
                'data' => [
                    ...$store->toArray(),
                    'joined_at' => $store->sellerProfile?->created_at ?? $store->created_at,
                    'categories' => $categories,
                    // JwtCookieToHeader chạy global nên user đăng nhập được nhận diện
                    // kể cả trên route public này (không cần middleware auth:api).
                    'is_following' => $this->ratingService->isFollowing(Auth::guard('api')->user(), $store->id),
                    'stats' => [
                        'completed_orders' => $completed,
                        'total_orders' => $total,
                        'orders_30d' => $orders30d,
                        'completion_rate' => $total > 0 ? (int) round($completed / $total * 100) : null,
                        'complaint_rate' => 0,
                        'rating' => $ratingStats['average'],
                        'rating_count' => $ratingStats['count'],
                        'followers' => $this->ratingService->followersCount($store->id),
                        'products_count' => $store->products_count,
                        'seller_level' => $level,
                    ],
                ],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy gian hàng',
            ], 404);
        }
    }
}
