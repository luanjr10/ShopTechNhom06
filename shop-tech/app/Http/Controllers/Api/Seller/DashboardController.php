<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\StoreFollow;
use Carbon\Carbon;

/**
 * Tổng quan MỘT gian hàng cho trang Dashboard seller — cùng cấu trúc số liệu
 * với Api\Admin\DashboardController nhưng luôn scope theo `$store->id`
 * (KHÔNG đụng dữ liệu của gian hàng khác, kể cả cùng 1 seller).
 */
class DashboardController extends Controller
{
    // [GET] /api/seller/stores/{store}/dashboard
    public function summary(Store $store)
    {
        $now = Carbon::now();
        $currentStart = $now->copy()->subDays(29)->startOfDay();
        $currentEnd = $now->copy()->endOfDay();
        $previousStart = $now->copy()->subDays(59)->startOfDay();
        $previousEnd = $now->copy()->subDays(30)->endOfDay();

        $completedOrders = SellerOrder::where('store_id', $store->id)->where('status', 'completed');

        // Khách hàng distinct cần join sang `orders` để lấy user_id (seller_orders không có cột này).
        $completedOrdersWithUser = SellerOrder::where('seller_orders.store_id', $store->id)
            ->where('seller_orders.status', 'completed')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id');

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'revenue' => $this->kpi((clone $completedOrders), 'completed_at', 'seller_amount', $currentStart, $currentEnd, $previousStart, $previousEnd),
                    'orders' => $this->kpi((clone $completedOrders), 'completed_at', '1', $currentStart, $currentEnd, $previousStart, $previousEnd, countOnly: true),
                    'customers' => $this->kpi((clone $completedOrdersWithUser), 'seller_orders.completed_at', 'orders.user_id', $currentStart, $currentEnd, $previousStart, $previousEnd, distinctColumn: 'orders.user_id'),
                ],
                'revenue_by_payment_method' => $this->revenueByPaymentMethod($store),
                'daily_revenue' => $this->dailySeries((clone $completedOrders), 'completed_at', 'seller_amount', $currentStart, $currentEnd),
                'top_categories' => $this->topCategories($store),
                'top_products' => $this->topProducts($store),
                'top_customers' => $this->topCustomers($store),
                'order_status_by_month' => $this->orderStatusByMonth($store),
                'recent_activity' => $this->recentActivity($store),
                'wallet' => $this->wallet($store),
            ],
        ], 200);
    }

    private function kpi(
        $query,
        string $dateColumn,
        $sumExpr,
        Carbon $currentStart,
        Carbon $currentEnd,
        Carbon $previousStart,
        Carbon $previousEnd,
        bool $countOnly = false,
        ?string $distinctColumn = null,
    ): array {
        if ($distinctColumn) {
            $current = (clone $query)->whereBetween($dateColumn, [$currentStart, $currentEnd])->distinct($distinctColumn)->count($distinctColumn);
            $previous = (clone $query)->whereBetween($dateColumn, [$previousStart, $previousEnd])->distinct($distinctColumn)->count($distinctColumn);
        } elseif ($countOnly) {
            $current = (clone $query)->whereBetween($dateColumn, [$currentStart, $currentEnd])->count();
            $previous = (clone $query)->whereBetween($dateColumn, [$previousStart, $previousEnd])->count();
        } else {
            $current = (float) (clone $query)->whereBetween($dateColumn, [$currentStart, $currentEnd])->sum($sumExpr);
            $previous = (float) (clone $query)->whereBetween($dateColumn, [$previousStart, $previousEnd])->sum($sumExpr);
        }

        $changePercent = $previous > 0
            ? round((($current - $previous) / $previous) * 100)
            : ($current > 0 ? 100 : 0);

        $series = $distinctColumn
            ? $this->dailyDistinctSeries($query, $dateColumn, $distinctColumn, $currentStart, $currentEnd)
            : $this->dailySeries($query, $dateColumn, $countOnly ? '1' : $sumExpr, $currentStart, $currentEnd);

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $changePercent,
            'labels' => array_values($series['labels']),
            'series' => array_values($series['values']),
        ];
    }

    private function dailySeries($query, string $dateColumn, $sumExpr, Carbon $start, Carbon $end): array
    {
        $rows = (clone $query)
            ->whereBetween($dateColumn, [$start, $end])
            ->selectRaw("DATE({$dateColumn}) as d, COALESCE(SUM({$sumExpr}), 0) as v")
            ->groupBy('d')
            ->pluck('v', 'd');

        return $this->fillDailyRange($rows, $start, $end);
    }

    /** Chuỗi theo ngày đếm SỐ LƯỢNG GIÁ TRỊ PHÂN BIỆT (VD: số khách hàng mới/ngày). */
    private function dailyDistinctSeries($query, string $dateColumn, string $distinctColumn, Carbon $start, Carbon $end): array
    {
        $rows = (clone $query)
            ->whereBetween($dateColumn, [$start, $end])
            ->selectRaw("DATE({$dateColumn}) as d, COUNT(DISTINCT {$distinctColumn}) as v")
            ->groupBy('d')
            ->pluck('v', 'd');

        return $this->fillDailyRange($rows, $start, $end);
    }

    private function fillDailyRange($rows, Carbon $start, Carbon $end): array
    {
        $labels = [];
        $values = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $key;
            $values[] = (float) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function revenueByPaymentMethod(Store $store): array
    {
        $start = Carbon::now()->subMonths(5)->startOfMonth();

        $rows = SellerOrder::where('seller_orders.store_id', $store->id)
            ->where('seller_orders.status', 'completed')
            ->where('seller_orders.completed_at', '>=', $start)
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->selectRaw("DATE_FORMAT(seller_orders.completed_at, '%Y-%m') as ym, orders.payment_method as method, COALESCE(SUM(seller_orders.subtotal),0) as total")
            ->groupBy('ym', 'method')
            ->get();

        $labels = [];
        $cod = [];
        $online = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 6; $i++) {
            $ym = $cursor->format('Y-m');
            $labels[] = $cursor->format('m-Y');
            $cod[] = (float) $rows->where('ym', $ym)->where('method', 'cod')->sum('total');
            $online[] = (float) $rows->where('ym', $ym)->where('method', '!=', 'cod')->sum('total');
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'cod' => $cod, 'online' => $online];
    }

    private function topCategories(Store $store): array
    {
        $completedSellerOrderIds = SellerOrder::where('store_id', $store->id)->where('status', 'completed')->pluck('id');

        $rows = OrderItem::whereIn('seller_order_id', $completedSellerOrderIds)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.name as name, COALESCE(SUM(order_items.line_total),0) as revenue')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        $top = $rows->take(5);
        $restTotal = $rows->skip(5)->sum('revenue');

        $result = $top->map(fn ($r) => ['name' => $r->name, 'revenue' => (float) $r->revenue])->values()->all();
        if ($restTotal > 0) {
            $result[] = ['name' => 'Khác', 'revenue' => (float) $restTotal];
        }

        return $result;
    }

    /** Top 5 sản phẩm bán chạy nhất (theo doanh thu) của gian hàng. */
    private function topProducts(Store $store): array
    {
        $completedSellerOrderIds = SellerOrder::where('store_id', $store->id)->where('status', 'completed')->pluck('id');

        $rows = OrderItem::whereIn('seller_order_id', $completedSellerOrderIds)
            ->selectRaw('product_id, product_name, COALESCE(SUM(line_total),0) as revenue, COALESCE(SUM(quantity),0) as quantity_sold')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return $rows->map(fn ($r) => [
            'product_id' => $r->product_id,
            'name' => $r->product_name,
            'revenue' => (float) $r->revenue,
            'quantity_sold' => (int) $r->quantity_sold,
        ])->all();
    }

    /** Top 5 khách hàng của RIÊNG gian hàng này (subtotal seller_orders completed). */
    private function topCustomers(Store $store): array
    {
        $rows = SellerOrder::where('seller_orders.store_id', $store->id)
            ->where('seller_orders.status', 'completed')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->selectRaw('users.id, users.name, users.avatar, users.google_avatar, COALESCE(SUM(seller_orders.subtotal),0) as total_spent, COUNT(*) as orders_count')
            ->groupBy('users.id', 'users.name', 'users.avatar', 'users.google_avatar')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return $rows->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'avatar_url' => $r->avatar ?? $r->google_avatar,
            'total_spent' => (float) $r->total_spent,
            'orders_count' => (int) $r->orders_count,
        ])->all();
    }

    private function orderStatusByMonth(Store $store): array
    {
        $start = Carbon::now()->subMonths(5)->startOfMonth();

        $completedRows = SellerOrder::where('store_id', $store->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(completed_at, '%Y-%m') as ym, COUNT(*) as c")
            ->groupBy('ym')
            ->pluck('c', 'ym');

        $cancelledRows = SellerOrder::where('store_id', $store->id)
            ->where('status', 'cancelled')
            ->where('updated_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as ym, COUNT(*) as c")
            ->groupBy('ym')
            ->pluck('c', 'ym');

        $labels = [];
        $completed = [];
        $cancelled = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 6; $i++) {
            $ym = $cursor->format('Y-m');
            $labels[] = $cursor->format('m-Y');
            $completed[] = (int) ($completedRows[$ym] ?? 0);
            $cancelled[] = (int) ($cancelledRows[$ym] ?? 0);
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'completed' => $completed, 'cancelled' => $cancelled];
    }

    /** Trộn 3 nguồn hoạt động gần đây của gian hàng: đơn hoàn tất, người theo dõi mới, đánh giá mới. */
    private function recentActivity(Store $store): array
    {
        $orders = SellerOrder::where('store_id', $store->id)
            ->where('status', 'completed')
            ->with(['order:id,user_id', 'order.user:id,name'])
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (SellerOrder $so) => [
                'type' => 'order_completed',
                'title' => "{$so->order?->user?->name} vừa hoàn tất đơn hàng",
                'amount' => (float) $so->seller_amount,
                'created_at' => $so->completed_at,
            ]);

        $followers = StoreFollow::where('store_id', $store->id)
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn (StoreFollow $f) => [
                'type' => 'new_follower',
                'title' => "{$f->user?->name} vừa theo dõi gian hàng",
                'amount' => null,
                'created_at' => $f->created_at,
            ]);

        $productIds = Product::where('store_id', $store->id)->pluck('id');
        $reviews = ProductReview::whereIn('product_id', $productIds)
            ->with(['user:id,name', 'product:id,name'])
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn (ProductReview $r) => [
                'type' => 'review',
                'title' => "{$r->user?->name} đánh giá {$r->rating}★ cho {$r->product?->name}",
                'amount' => null,
                'created_at' => $r->created_at,
            ]);

        return $orders->concat($followers)->concat($reviews)
            ->sortByDesc(fn ($item) => $item['created_at'])
            ->take(8)
            ->values()
            ->all();
    }

    private function wallet(Store $store): array
    {
        $wallet = SellerWallet::where('seller_profile_id', $store->seller_profile_id)->first();

        return [
            'balance' => (float) ($wallet->balance ?? 0),
            'pending_balance' => (float) ($wallet->pending_balance ?? 0),
            'withdrawable_balance' => (float) ($wallet->withdrawable_balance ?? 0),
        ];
    }
}
