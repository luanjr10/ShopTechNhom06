<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Store;
use App\Services\InstallmentService;
use App\Services\MomoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InstallmentController extends Controller
{
    public function __construct(
        private InstallmentService $installmentService,
        private MomoService $momoService,
    ) {}

    /**
     * Điều kiện trả góp do gian hàng đặt (kỳ hạn, lãi, đơn tối thiểu). Công khai để trang sản phẩm hiển thị.
     */
    public function options(Request $request)
    {
        $validated = $request->validate(['store_id' => 'required|integer|exists:stores,id']);
        $store = Store::findOrFail($validated['store_id']);

        return response()->json([
            'success' => true,
            'data' => ['store_id' => $store->id, 'store_name' => $store->name] + $this->installmentService->settingsFor($store),
        ], 200);
    }

    public function summary(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->installmentService->summary($request->user())], 200);
    }

    public function index(Request $request)
    {
        $plans = InstallmentPlan::where('user_id', $request->user()->id)
            ->with(['payments', 'store:id,name,slug', 'order.sellerOrders.items:id,seller_order_id,product_name,quantity'])
            ->latest()
            ->get()
            ->map(function (InstallmentPlan $plan) {
                $products = $plan->order->sellerOrders->flatMap->items->pluck('product_name')->values();
                $paid = $plan->payments->where('status', 'paid');

                return [
                    'id' => $plan->id,
                    'order_id' => $plan->order_id,
                    'store' => $plan->store,
                    'status' => $plan->status,
                    'decision_note' => $plan->decision_note,
                    'decided_at' => $plan->decided_at,
                    'principal' => (float) $plan->principal,
                    'months' => $plan->months,
                    'monthly_rate' => (float) $plan->monthly_rate,
                    'total_interest' => (float) $plan->total_interest,
                    'total_payable' => (float) $plan->total_payable,
                    'paid_amount' => (float) $paid->sum('amount'),
                    'remaining_amount' => (float) $plan->payments->where('status', 'pending')->sum('amount'),
                    'product_names' => $products,
                    'created_at' => $plan->created_at,
                    'payments' => $plan->payments->values(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => ['plans' => $plans, 'summary' => $this->installmentService->summary($request->user())],
        ], 200);
    }

    public function pay(Request $request, InstallmentPayment $payment)
    {
        $payment->load('plan.order');
        $plan = $payment->plan;

        if ($plan->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy kỳ trả góp'], 404);
        }

        if ($plan->status === 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Gian hàng chưa duyệt yêu cầu trả góp này'], 422);
        }

        if ($payment->status !== 'pending' || $plan->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Kỳ trả góp này không còn cần thanh toán'], 422);
        }

        $earlierUnpaid = $plan->payments()->where('number', '<', $payment->number)->where('status', 'pending')->exists();
        if ($earlierUnpaid) {
            return response()->json(['success' => false, 'message' => 'Vui lòng thanh toán các kỳ trước theo thứ tự'], 422);
        }

        try {
            $payUrl = $this->momoService->createInstallmentPaymentUrl($payment);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }

        return response()->json(['success' => true, 'data' => ['pay_url' => $payUrl]], 200);
    }

    /**
     * MoMo gọi về cả bằng redirect (GET) lẫn IPN (POST); cả hai dùng chung xử lý idempotent.
     */
    public function momoReturn(Request $request)
    {
        $data = $request->all();
        $valid = $this->momoService->verifySignature($data);
        $payment = InstallmentPayment::with('plan.order')->where('payment_ref', $data['orderId'] ?? null)->first();
        $success = $payment && $valid && (int) ($data['resultCode'] ?? 1) === 0;

        if ($payment && $success) {
            $this->installmentService->markPaid($payment);

            $order = $payment->plan->order;
            if ($payment->number === 1 && $order->status === 'pending' && ! $order->paid_at) {
                $order->update(['status' => 'paid', 'paid_at' => now()]);
            }
        }

        if (! $payment || ! $valid) {
            Log::warning('MoMo trả góp: phản hồi không hợp lệ', $data);
        }

        if ($request->isMethod('post')) {
            return response()->json(['partnerCode' => $data['partnerCode'] ?? null, 'resultCode' => 0, 'message' => 'success'], 200);
        }

        $base = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($base.'/tai-khoan/tra-gop?pay='.($success ? 'success' : 'failed'));
    }
}
