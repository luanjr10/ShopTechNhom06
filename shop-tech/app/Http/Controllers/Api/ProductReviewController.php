<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\CloudinaryService;
use App\Services\RatingService;
use Illuminate\Http\Request;

/**
 * Đánh giá sao + bình luận cho 1 sản phẩm — public xem, cần đăng nhập để gửi.
 * 1 khách chỉ có 1 đánh giá/sản phẩm (gửi lại = sửa, xem store()).
 */
class ProductReviewController extends Controller
{
    public function __construct(
        private CloudinaryService $cloudinaryService,
        private RatingService $ratingService,
    ) {}

    // [GET] /api/products/{product}/reviews
    public function index(Request $request, Product $product)
    {
        $query = ProductReview::where('product_id', $product->id)
            ->with('user:id,name,username,avatar,google_avatar')
            ->latest();

        if ($rating = $request->input('rating')) {
            $query->where('rating', (int) $rating);
        }

        if ($request->boolean('verified')) {
            $query->whereNotNull('order_item_id');
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 10)),
            'stats' => $this->ratingService->productStats($product->id),
        ], 200);
    }

    // [POST] /api/products/{product}/reviews
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = $request->user();

        $orderItemId = OrderItem::where('product_id', $product->id)
            ->whereHas('sellerOrder', function ($q) use ($user) {
                $q->where('status', 'completed')
                    ->whereHas('order', fn ($q2) => $q2->where('user_id', $user->id));
            })
            ->latest('id')
            ->value('id');

        $attributes = [
            'order_item_id' => $orderItemId,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ];

        // Chỉ ghi đè ảnh khi khách gửi ảnh mới — gửi lại KHÔNG kèm ảnh (sửa
        // rating/comment) sẽ giữ nguyên ảnh cũ thay vì xoá mất.
        if ($request->hasFile('images')) {
            $attributes['images'] = $this->cloudinaryService->uploadMultipleImages($request->file('images'), 'reviews-shoptech');
        }

        $review = ProductReview::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $user->id],
            $attributes,
        );

        return response()->json([
            'success' => true,
            'message' => $review->wasRecentlyCreated ? 'Đã gửi đánh giá — cảm ơn bạn!' : 'Đã cập nhật đánh giá của bạn',
            'data' => $review->load('user:id,name,username,avatar,google_avatar'),
        ], $review->wasRecentlyCreated ? 201 : 200);
    }
}
