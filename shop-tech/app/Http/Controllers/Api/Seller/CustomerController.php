<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Services\CustomerTierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Seller xem danh sách/chi tiết khách hàng ĐÃ TỪNG MUA HÀNG TẠI GIAN HÀNG của
 * mình — CHỈ XEM. Hạng thành viên (tier) vẫn tính TOÀN SÀN (xem
 * CustomerTierService), "store_spent" mới là số seller thực sự bán được cho
 * khách đó tại store này.
 */
class CustomerController extends Controller
{
    public function __construct(private CustomerTierService $tierService) {}

    // [GET] /api/seller/stores/{store}/customers
    public function index(Request $request, Store $store)
    {
        $rowsQuery = DB::table('seller_orders')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('seller_orders.store_id', $store->id)
            ->select('users.id', 'users.name', 'users.username', 'users.email', 'users.phone')
            ->selectRaw("SUM(CASE WHEN seller_orders.status = 'completed' THEN seller_orders.subtotal ELSE 0 END) as store_spent")
            ->selectRaw('COUNT(DISTINCT orders.id) as store_orders_count')
            ->selectRaw('MAX(orders.created_at) as last_order_at')
            ->groupBy('users.id', 'users.name', 'users.username', 'users.email', 'users.phone');

        if ($search = $request->input('search')) {
            $rowsQuery->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $total = DB::table(DB::raw("({$rowsQuery->toSql()}) as t"))
            ->mergeBindings($rowsQuery)
            ->count();

        $rows = $rowsQuery->orderByDesc('store_spent')
            ->forPage($page, $perPage)
            ->get();

        $userIds = $rows->pluck('id')->all();
        $spentByUser = $this->tierService->totalSpentForUsers($userIds);

        $data = $rows->map(function ($row) use ($spentByUser) {
            $totalSpent = $spentByUser[$row->id] ?? 0.0;
            $tier = $this->tierService->resolve($totalSpent);

            return [
                'id' => $row->id,
                'name' => $row->name,
                'username' => $row->username,
                'email' => $row->email,
                'phone' => $row->phone,
                'store_spent' => (float) $row->store_spent,
                'store_orders_count' => (int) $row->store_orders_count,
                'last_order_at' => $row->last_order_at,
                'total_spent' => $totalSpent,
                'tier' => $tier,
                'tier_label' => $this->tierService->label($tier),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $data,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ], 200);
    }

    // [GET] /api/seller/stores/{store}/customers/{customer}
    public function show(Store $store, User $customer)
    {
        $hasOrderedHere = DB::table('seller_orders')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->where('seller_orders.store_id', $store->id)
            ->where('orders.user_id', $customer->id)
            ->exists();

        abort_unless($hasOrderedHere, 404, 'Khách hàng chưa từng mua tại gian hàng này');

        $sellerOrders = DB::table('seller_orders')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->where('seller_orders.store_id', $store->id)
            ->where('orders.user_id', $customer->id)
            ->select('seller_orders.id', 'seller_orders.order_id', 'seller_orders.status')
            ->selectRaw('seller_orders.subtotal as total_amount')
            ->selectRaw('orders.created_at as created_at')
            ->orderByDesc('seller_orders.id')
            ->limit(30)
            ->get();

        $totalSpent = $this->tierService->totalSpentForUser($customer->id);
        $tier = $this->tierService->resolve($totalSpent);
        $storeSpent = (float) DB::table('seller_orders')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->where('seller_orders.store_id', $store->id)
            ->where('orders.user_id', $customer->id)
            ->where('seller_orders.status', 'completed')
            ->sum('seller_orders.subtotal');

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => collect($customer->toArray())->only(['id', 'name', 'username', 'email', 'phone', 'avatar_url', 'created_at']),
                'tier' => $tier,
                'tier_label' => $this->tierService->label($tier),
                'total_spent' => $totalSpent,
                'store_spent' => $storeSpent,
                'seller_orders' => $sellerOrders,
            ],
        ], 200);
    }
}
