<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\InstallmentPlan;
use App\Models\Store;
use App\Notifications\InstallmentDecisionNotification;
use App\Services\InstallmentService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * Bán trả góp của gian hàng: cấu hình điều kiện cho vay và quyết định từng yêu cầu của khách.
 */
class InstallmentController extends Controller
{
    public function __construct(
        private InstallmentService $installments,
        private OrderService $orders,
    ) {}

    public function settings(Store $store)
    {
        return response()->json(['success' => true, 'data' => $this->installments->settingsFor($store)], 200);
    }

    public function updateSettings(Request $request, Store $store)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'min_order' => 'required|numeric|min:0',
            'terms' => 'required|array|min:1|max:'.InstallmentService::MAX_TERMS,
            'terms.*.months' => 'required|integer|min:2|max:36|distinct',
            'terms.*.monthly_rate' => 'required|numeric|min:0|max:5',
        ]);

        $this->installments->updateSettings($store, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật điều kiện bán trả góp',
            'data' => $this->installments->settingsFor($store),
        ], 200);
    }

    public function index(Request $request, Store $store)
    {
        $query = InstallmentPlan::with(['user', 'payments', 'order.sellerOrders.items:id,seller_order_id,product_name,quantity'])
            ->where('store_id', $store->id)
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('overdue')) {
            $query->where('status', 'active')->whereHas('payments', function ($q) {
                $q->where('status', 'pending')->where('due_date', '<', now('Asia/Ho_Chi_Minh')->toDateString());
            });
        }

        $plans = $query->paginate((int) $request->input('per_page', 15));

        $plans->getCollection()->transform(function (InstallmentPlan $plan) use ($store) {
            $user = $plan->user;
            $plan->setAttribute('customer', ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]);
            $plan->setAttribute('customer_profile', $plan->status === 'pending_approval' ? $this->installments->customerProfile($user, $store) : null);
            $plan->setAttribute('product_names', $plan->order->sellerOrders->flatMap->items->pluck('product_name')->values());
            $plan->setAttribute('paid_amount', (float) $plan->payments->where('status', 'paid')->sum('amount'));
            $plan->setAttribute('remaining_amount', (float) $plan->payments->where('status', 'pending')->sum('amount'));
            $plan->setAttribute('overdue_count', $plan->payments->where('is_overdue', true)->count());
            $plan->unsetRelation('user');
            $plan->unsetRelation('order');

            return $plan;
        });

        return response()->json([
            'success' => true,
            'data' => $plans,
            'stats' => [
                'pending_approval' => InstallmentPlan::where('store_id', $store->id)->where('status', 'pending_approval')->count(),
                'active_plans' => InstallmentPlan::where('store_id', $store->id)->where('status', 'active')->count(),
            ],
        ], 200);
    }

    public function decide(Request $request, Store $store, InstallmentPlan $plan)
    {
        abort_unless($plan->store_id === $store->id, 404, 'Không tìm thấy yêu cầu trả góp');

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => 'nullable|string|max:255',
        ]);

        try {
            if ($validated['decision'] === 'approve') {
                $this->installments->approve($plan, $validated['note'] ?? null);
            } else {
                $this->installments->reject($plan, $validated['note'] ?? null);
                $this->orders->cancel($plan->order);
            }
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $plan->refresh()->load('store:id,name');

        try {
            $plan->user->notify(new InstallmentDecisionNotification($plan, $validated['decision'] === 'approve'));
        } catch (Throwable $e) {
            Log::warning('Không gửi được mail quyết định trả góp', ['plan_id' => $plan->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => $validated['decision'] === 'approve'
                ? 'Đã chấp nhận cho vay trả góp. Khách sẽ thanh toán kỳ 1 để bắt đầu.'
                : 'Đã từ chối và hủy đơn hàng của khách.',
            'data' => $plan,
        ], 200);
    }
}
