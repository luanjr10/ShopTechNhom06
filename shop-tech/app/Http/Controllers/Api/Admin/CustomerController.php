<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerTierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin xem danh sách/chi tiết khách hàng (role=customer) TOÀN SÀN — CHỈ XEM,
 * không có sửa/xoá (khách tự quản lý hồ sơ của họ, xem routes/api/account.php).
 */
class CustomerController extends Controller
{
    public function __construct(private CustomerTierService $tierService) {}

    // [GET] /api/admin/customers
    public function index(Request $request)
    {
        $query = $this->baseQuery();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Hạng là suy ra từ total_spent (không lưu cột riêng) — lọc bằng HAVING
        // trên chính alias total_spent đã SELECT ở baseQuery(), để paginate() ra
        // đúng tổng số bản ghi/số trang thay vì lọc sau khi đã cắt trang.
        if ($tier = $request->input('tier')) {
            [$min, $max] = $this->tierService->rangeForTier($tier);
            $query->havingRaw('total_spent >= ?', [$min]);
            if ($max !== null) {
                $query->havingRaw('total_spent < ?', [$max]);
            }
        }

        $query->orderByDesc('total_spent');

        $paginated = $query->paginate((int) $request->input('per_page', 15));

        $paginated->getCollection()->transform(fn (User $user) => $this->present($user));

        return response()->json(['success' => true, 'data' => $paginated], 200);
    }

    // [GET] /api/admin/customers/{customer}
    public function show(User $customer)
    {
        abort_unless($customer->role === 'customer', 404, 'Không tìm thấy khách hàng');

        $totalSpent = $this->tierService->totalSpentForUser($customer->id);
        $tier = $this->tierService->resolve($totalSpent);

        $orders = $customer->orders()
            ->with('sellerOrders.store:id,name')
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer->load('addresses'),
                'tier' => $tier,
                'tier_label' => $this->tierService->label($tier),
                'total_spent' => $totalSpent,
                'orders_count' => $customer->orders()->whereIn('status', CustomerTierService::TIER_STATUSES)->count(),
                'next_tier' => $this->tierService->nextTier($totalSpent),
                'orders' => $orders,
            ],
        ], 200);
    }

    private function baseQuery()
    {
        $totalSpentSub = DB::table('orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0)')
            ->whereColumn('orders.user_id', 'users.id')
            ->whereIn('status', CustomerTierService::TIER_STATUSES);

        $ordersCountSub = DB::table('orders')
            ->selectRaw('COUNT(*)')
            ->whereColumn('orders.user_id', 'users.id')
            ->whereIn('status', CustomerTierService::TIER_STATUSES);

        return User::query()
            ->where('role', 'customer')
            ->select('users.*')
            ->addSelect(['total_spent' => $totalSpentSub])
            ->addSelect(['orders_count' => $ordersCountSub]);
    }

    private function present(User $user): array
    {
        $totalSpent = (float) $user->total_spent;
        $tier = $this->tierService->resolve($totalSpent);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'created_at' => $user->created_at,
            'total_spent' => $totalSpent,
            'orders_count' => (int) $user->orders_count,
            'tier' => $tier,
            'tier_label' => $this->tierService->label($tier),
        ];
    }
}
