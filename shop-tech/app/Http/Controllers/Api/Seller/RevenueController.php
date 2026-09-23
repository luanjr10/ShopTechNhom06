<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\Store;
use Illuminate\Http\Request;

/**
 * Doanh thu gian hàng: tổng hợp từ seller_orders (chỉ đơn completed).
 * commission_amount/seller_amount là số đã snapshot lúc tạo/hoàn tất đơn.
 */
class RevenueController extends Controller
{
    // [GET] /api/seller/stores/{store}/revenue?days=30
    public function summary(Request $request, Store $store)
    {
        $days = (int) $request->input('days', 30);

        $completed = SellerOrder::where('store_id', $store->id)->where('status', 'completed');

        $since = now()->subDays($days - 1)->startOfDay();

        // Thẻ tổng quan phải khớp đúng khoảng "$days" đang chọn — trước đây
        // chỉ mỗi biểu đồ theo ngày lọc theo $since, còn 4 thẻ số phía trên
        // luôn tính all-time, khiến đổi 7/30/90 ngày không đổi số liệu thẻ.
        $totals = (clone $completed)
            ->where('completed_at', '>=', $since)
            ->selectRaw(
                'COUNT(*) as orders_count, COALESCE(SUM(subtotal),0) as gross_revenue,
             COALESCE(SUM(commission_amount),0) as commission_paid,
             COALESCE(SUM(seller_amount),0) as net_revenue'
            )->first();

        $series = (clone $completed)
            ->where('completed_at', '>=', $since)
            ->selectRaw('DATE(completed_at) as date, COALESCE(SUM(seller_amount),0) as revenue, COUNT(*) as orders_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'orders_count' => (int) $totals->orders_count,
                'gross_revenue' => (float) $totals->gross_revenue,
                'commission_paid' => (float) $totals->commission_paid,
                'net_revenue' => (float) $totals->net_revenue,
                'series' => $series,
            ],
        ], 200);
    }
}
