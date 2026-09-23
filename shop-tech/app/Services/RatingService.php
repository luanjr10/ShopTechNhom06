<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\StoreFollow;
use App\Models\User;

/**
 * Thống kê đánh giá (sản phẩm/gian hàng) + theo dõi gian hàng — dùng chung cho
 * trang public (store/product) lẫn trang quản lý (admin/seller).
 */
class RatingService
{
    /**
     * @return array{average: float, count: int, breakdown: array<int, int>}
     */
    public function productStats(int $productId): array
    {
        $rows = ProductReview::where('product_id', $productId)
            ->selectRaw('rating, COUNT(*) as c')
            ->groupBy('rating')
            ->pluck('c', 'rating');

        $count = (int) $rows->sum();
        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $breakdown[$star] = (int) ($rows[$star] ?? 0);
        }

        $weighted = array_sum(array_map(fn ($star, $c) => $star * $c, array_keys($breakdown), $breakdown));

        return [
            'average' => $count > 0 ? round($weighted / $count, 1) : 0.0,
            'count' => $count,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return array{average: float, count: int}
     */
    public function storeStats(int $storeId): array
    {
        $productIds = Product::where('store_id', $storeId)->pluck('id');

        if ($productIds->isEmpty()) {
            return ['average' => 0.0, 'count' => 0];
        }

        $count = ProductReview::whereIn('product_id', $productIds)->count();
        $avg = $count > 0 ? (float) ProductReview::whereIn('product_id', $productIds)->avg('rating') : 0.0;

        return ['average' => round($avg, 1), 'count' => $count];
    }

    public function followersCount(int $storeId): int
    {
        return StoreFollow::where('store_id', $storeId)->count();
    }

    public function isFollowing(?User $user, int $storeId): bool
    {
        return $user !== null && StoreFollow::where('store_id', $storeId)->where('user_id', $user->id)->exists();
    }
}
