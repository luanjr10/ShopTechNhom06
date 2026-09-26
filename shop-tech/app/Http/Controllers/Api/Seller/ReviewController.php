<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Store;
use App\Services\RatingService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private RatingService $ratingService) {}

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
