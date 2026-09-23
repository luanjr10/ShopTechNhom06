<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Store;
use App\Services\RatingService;
use Illuminate\Http\Request;

/**
 * Seller xem đánh giá của các sản phẩm trong gian hàng mình — CHỈ XEM (không
 * xoá được, tránh xung đột lợi ích khi tự xoá đánh giá xấu về mình).
 */
class ReviewController extends Controller
{
    public function __construct(private RatingService $ratingService) {}

    // [GET] /api/seller/stores/{store}/reviews
    public function index(Request $request, Store $store)
    {
        $query = ProductReview::whereHas('product', fn ($q) => $q->where('store_id', $store->id))
            ->with(['product:id,name', 'user:id,name,email'])
            ->latest();

        if ($rating = $request->input('rating')) {
            $query->where('rating', (int) $rating);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
            'stats' => $this->ratingService->storeStats($store->id),
        ], 200);
    }
}
