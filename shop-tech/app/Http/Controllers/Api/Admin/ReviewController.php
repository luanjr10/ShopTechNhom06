<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductReview::with(['product:id,name,store_id', 'product.store:id,name', 'user:id,name,email'])
            ->latest();

        if ($rating = $request->input('rating')) {
            $query->where('rating', (int) $rating);
        }

        if ($storeId = $request->input('store_id')) {
            $query->whereHas('product', fn ($q) => $q->where('store_id', (int) $storeId));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    public function destroy(ProductReview $review)
    {
        $review->delete();

        return response()->json(['success' => true, 'message' => 'Đã xoá đánh giá'], 200);
    }
}
