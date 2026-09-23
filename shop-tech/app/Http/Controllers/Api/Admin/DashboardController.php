<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use Carbon\Carbon;

/**
 * Tổng quan toàn sàn cho trang Dashboard admin — TOÀN BỘ số liệu tính thật từ
 * DB (không có mock), doanh thu sàn = SUM(seller_orders.commission_amount)
 * (phần hoa hồng, xem CommissionService/SellerOrderService::complete — đây là
 * số ShopTech thực nhận), chỉ tính trên seller_orders đã 'completed'.
 */
class DashboardController extends Controller
{
    // [GET] /api/admin/dashboard
    public function summary()
    {
        $now = Carbon::now();
        $currentStart = $now->copy()->subDays(29)->startOfDay();
        $currentEnd = $now->copy()->endOfDay();
        $previousStart = $now->copy()->subDays(59)->startOfDay();
        $previousEnd = $now->copy()->subDays(30)->endOfDay();

        $completedOrders = SellerOrder::where('status', 'completed');

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'revenue' => $this->kpi(
                        (clone $completedOrders),
                        'completed_at',
                        'commission_amount',
                        $currentStart,
                        $currentEnd,
                        $previousStart,
                        $previousEnd,
                    ),
                    'orders' => $this->kpi(
                        (clone $completedOrders),
                        'completed_at',
                        '1',
                        $currentStart,
                        $currentEnd,
                        $previousStart,
                        $previousEnd,
                        countOnly: true,
                    ),
                    'new_users' => $this->kpi(
                        User::query(),
                        'created_at',
                        '1',
                        $currentStart,
                        $currentEnd,
                        $previousStart,
                        $previousEnd,
                        countOnly: true,
                    ),
                ],
                'revenue_by_payment_method' => $this->revenueByPaymentMethod(),
                'daily_revenue' => $this->dailySeries((clone $completedOrders), 'completed_at', 'commission_amount', $currentStart, $currentEnd),
                'top_categories' => $this->topCategories(),
                'top_stores' => $this->topStores(),
                'top_customers' => $this->topCustomers(),
                'order_status_by_month' => $this->orderStatusByMonth(),
                'recent_activity' => $this->recentActivity(),
                'platform_funds' => $this->platformFunds($now),
            ],
        ], 200);
    }

    /**
     * KPI dạng "hiện tại 30 ngày vs 30 ngày trước" + chuỗi theo ngày (sparkline).
     */
    private function kpi(
        $query,
        string $dateColumn,
        $sumExpr,
        Carbon $currentStart,
        Carbon $currentEnd,
        Carbon $previousStart,
        Carbon $previousEnd,
        bool $countOnly = false,
    ): array {
        $current = $countOnly
            ? (clone $query)->whereBetween($dateColumn, [$currentStart, $currentEnd])->count()
            : (float) (clone $query)->whereBetween($dateColumn, [$currentStart, $currentEnd])->sum($sumExpr);

        $previous = $countOnly
            ? (clone $query)->whereBetween($dateColumn, [$previousStart, $previousEnd])->count()
            : (float) (clone $query)->whereBetween($dateColumn, [$previousStart, $previousEnd])->sum($sumExpr);

        $changePercent = $previous > 0
            ? round((($current - $previous) / $previous) * 100)
            : ($current > 0 ? 100 : 0);

        $series = $countOnly
            ? $this->dailySeries($query, $dateColumn, '1', $currentStart, $currentEnd)
            : $this->dailySeries($query, $dateColumn, $sumExpr, $currentStart, $currentEnd);

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $changePercent,
            'labels' => array_values($series['labels']),
            'series' => array_values($series['values']),
        ];
    }

    /**
     * Chuỗi giá trị theo NGÀY trong khoảng [start,end] — tự điền 0 cho ngày
     * không có dữ liệu (biểu đồ sparkline/line cần đủ điểm, không được nhảy cóc).
     */
    private function dailySeries($query, string $dateColumn, $sumExpr, Carbon $start, Carbon $end): array
    {
        $rows = (clone $query)
            ->whereBetween($dateColumn, [$start, $end])
            ->selectRaw("DATE({$dateColumn}) as d, COALESCE(SUM({$sumExpr}), 0) as v")
            ->groupBy('d')
            ->pluck('v', 'd');

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

    /**
     * Doanh thu (subtotal seller_orders completed) theo tháng, tách COD vs
     * thanh toán online (momo/vnpay/onepay/sepay) — 6 tháng gần nhất.
     */
    private function revenueByPaymentMethod(): array
    {
        $start = Carbon::now()->subMonths(5)->startOfMonth();

        $rows = SellerOrder::where('seller_orders.status', 'completed')
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
            $codTotal = $rows->where('ym', $ym)->where('method', 'cod')->sum('total');
            $onlineTotal = $rows->where('ym', $ym)->where('method', '!=', 'cod')->sum('total');
            $cod[] = (float) $codTotal;
            $online[] = (float) $onlineTotal;
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'cod' => $cod, 'online' => $online];
    }

    /**
     * Top 5 danh mục theo doanh thu (line_total order_items thuộc seller_orders
     * completed), gộp phần còn lại vào "Khác" — dùng cho doughnut chart.
     */
    private function topCategories(): array
    {
        $completedSellerOrderIds = SellerOrder::where('status', 'completed')->pluck('id');

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

    /** Top 5 gian hàng theo doanh thu thực nhận (seller_amount, completed). */
    private function topStores(): array
    {
        $rows = SellerOrder::where('seller_orders.status', 'completed')
            ->join('stores', 'stores.id', '=', 'seller_orders.store_id')
            ->selectRaw('stores.id, stores.name, stores.logo, COALESCE(SUM(seller_orders.seller_amount),0) as revenue, COUNT(*) as orders_count')
            ->groupBy('stores.id', 'stores.name', 'stores.logo')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return $rows->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'logo' => $r->logo,
            'revenue' => (float) $r->revenue,
            'orders_count' => (int) $r->orders_count,
        ])->all();
    }

    /** Top 5 khách hàng theo tổng chi tiêu (Order.total_amount, đơn completed). */
    private function topCustomers(): array
    {
        $rows = Order::where('orders.status', 'completed')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->selectRaw('users.id, users.name, users.avatar, users.google_avatar, COALESCE(SUM(orders.total_amount),0) as total_spent, COUNT(*) as orders_count')
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

    /**
     * Số đơn hoàn tất vs huỷ theo tháng (6 tháng) — huỷ dùng `updated_at` làm
     * mốc gần đúng (bảng seller_orders không có cột `cancelled_at` riêng).
     */
    private function orderStatusByMonth(): array
    {
        $start = Carbon::now()->subMonths(5)->startOfMonth();

        $completedRows = SellerOrder::where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(completed_at, '%Y-%m') as ym, COUNT(*) as c")
            ->groupBy('ym')
            ->pluck('c', 'ym');

        $cancelledRows = SellerOrder::where('status', 'cancelled')
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

    /** Trộn 3 nguồn hoạt động gần đây (đơn hoàn tất, gian hàng mới duyệt, đánh giá mới) — lấy 8 mới nhất. */
    private function recentActivity(): array
    {
        $orders = SellerOrder::where('status', 'completed')
            ->with(['store:id,name', 'order:id,user_id', 'order.user:id,name'])
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (SellerOrder $so) => [
                'type' => 'order_completed',
                'title' => "{$so->order?->user?->name} vừa hoàn tất đơn tại {$so->store?->name}",
                'amount' => (float) $so->seller_amount,
                'created_at' => $so->completed_at,
            ]);

        $sellers = SellerProfile::where('status', 'active')
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn (SellerProfile $sp) => [
                'type' => 'seller_joined',
                'title' => "{$sp->user?->name} vừa trở thành người bán",
                'amount' => null,
                'created_at' => $sp->created_at,
            ]);

        $reviews = ProductReview::with(['user:id,name', 'product:id,name'])
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn (ProductReview $r) => [
                'type' => 'review',
                'title' => "{$r->user?->name} đánh giá {$r->rating}★ cho {$r->product?->name}",
                'amount' => null,
                'created_at' => $r->created_at,
            ]);

        return $orders->concat($sellers)->concat($reviews)
            ->sortByDesc(fn ($item) => $item['created_at'])
            ->take(8)
            ->values()
            ->all();
    }

    /** Doanh thu sàn (GMV) + hoa hồng thực nhận tháng này vs tháng trước. */
    private function platformFunds(Carbon $now): array
    {
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $now->copy()->startOfMonth()->subSecond();

        $thisMonth = SellerOrder::where('status', 'completed')
            ->where('completed_at', '>=', $thisMonthStart)
            ->selectRaw('COALESCE(SUM(subtotal),0) as gmv, COALESCE(SUM(commission_amount),0) as commission')
            ->first();

        $lastMonth = SellerOrder::where('status', 'completed')
            ->whereBetween('completed_at', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('COALESCE(SUM(subtotal),0) as gmv, COALESCE(SUM(commission_amount),0) as commission')
            ->first();

        return [
            'gmv_this_month' => (float) $thisMonth->gmv,
            'commission_this_month' => (float) $thisMonth->commission,
            'gmv_last_month' => (float) $lastMonth->gmv,
            'commission_last_month' => (float) $lastMonth->commission,
        ];
    }
}
