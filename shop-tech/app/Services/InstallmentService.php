<?php

namespace App\Services;

use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\StoreInstallmentSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trả góp do từng gian hàng cho vay: gian hàng tự bật, đặt kỳ hạn và lãi, rồi tự quyết định có chấp nhận từng
 * yêu cầu của khách hay không. Khách trả từng kỳ qua MoMo sandbox; tiền mỗi kỳ (trừ hoa hồng sàn trên phần gốc)
 * về ví của gian hàng, nên gian hàng là bên chịu rủi ro tín dụng và hưởng toàn bộ tiền lãi.
 */
class InstallmentService
{
    public const MAX_TERMS = 6;

    public function __construct(private WalletService $walletService) {}

    /**
     * @return array{enabled: bool, min_order: float, options: list<array{months: int, monthly_rate: float}>}
     */
    public function settingsFor(Store|int $store): array
    {
        $storeId = $store instanceof Store ? $store->id : $store;
        $setting = StoreInstallmentSetting::where('store_id', $storeId)->first();

        $terms = collect($setting?->terms ?: StoreInstallmentSetting::DEFAULT_TERMS)
            ->map(fn (array $t) => ['months' => (int) $t['months'], 'monthly_rate' => (float) $t['monthly_rate']])
            ->sortBy('months')
            ->values()
            ->all();

        return [
            'enabled' => (bool) ($setting?->enabled ?? false),
            'min_order' => (float) ($setting?->min_order ?? StoreInstallmentSetting::DEFAULT_MIN_ORDER),
            'options' => $terms,
        ];
    }

    /**
     * @param  array{enabled: bool, min_order: numeric, terms: list<array{months: int, monthly_rate: numeric}>}  $values
     */
    public function updateSettings(Store $store, array $values): StoreInstallmentSetting
    {
        return StoreInstallmentSetting::updateOrCreate(['store_id' => $store->id], [
            'enabled' => (bool) $values['enabled'],
            'min_order' => $values['min_order'],
            'terms' => collect($values['terms'])
                ->map(fn (array $t) => ['months' => (int) $t['months'], 'monthly_rate' => (float) $t['monthly_rate']])
                ->sortBy('months')
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array{months: int, monthly_rate: float, principal: float, total_interest: float, total_payable: float, schedule: list<array{number: int, amount: float, principal_part: float, interest_part: float}>}
     */
    public function quote(float $principal, int $months, float $monthlyRate): array
    {
        $principal = round($principal);
        $totalInterest = round($principal * $monthlyRate / 100 * $months);

        $schedule = [];
        $principalLeft = $principal;
        $interestLeft = $totalInterest;

        for ($n = 1; $n <= $months; $n++) {
            $isLast = $n === $months;
            $principalPart = $isLast ? $principalLeft : floor($principal / $months);
            $interestPart = $isLast ? $interestLeft : round($totalInterest / $months);

            $schedule[] = [
                'number' => $n,
                'amount' => $principalPart + $interestPart,
                'principal_part' => $principalPart,
                'interest_part' => $interestPart,
            ];

            $principalLeft -= $principalPart;
            $interestLeft -= $interestPart;
        }

        return [
            'months' => $months,
            'monthly_rate' => $monthlyRate,
            'principal' => $principal,
            'total_interest' => $totalInterest,
            'total_payable' => $principal + $totalInterest,
            'schedule' => $schedule,
        ];
    }

    public function hasOverdue(User $user): bool
    {
        return InstallmentPayment::where('status', 'pending')
            ->where('due_date', '<', now('Asia/Ho_Chi_Minh')->toDateString())
            ->whereHas('plan', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->exists();
    }

    public function outstandingPrincipal(User $user): float
    {
        return (float) InstallmentPayment::where('status', 'pending')
            ->whereHas('plan', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->sum('principal_part');
    }

    /**
     * @return array{has_overdue: bool, outstanding: float}
     */
    public function summary(User $user): array
    {
        return ['has_overdue' => $this->hasOverdue($user), 'outstanding' => $this->outstandingPrincipal($user)];
    }

    public function assertCanRequest(User $user, Store $store, float $principal, int $months): void
    {
        $settings = $this->settingsFor($store);

        if (! $settings['enabled']) {
            throw new RuntimeException("Gian hàng {$store->name} hiện không nhận bán trả góp.");
        }

        if ($principal < $settings['min_order']) {
            throw new RuntimeException('Gian hàng này chỉ bán trả góp cho đơn từ '.number_format($settings['min_order'], 0, ',', '.').'đ.');
        }

        if (collect($settings['options'])->doesntContain('months', $months)) {
            throw new RuntimeException('Gian hàng này không có kỳ hạn trả góp '.$months.' tháng.');
        }

        if ($this->hasOverdue($user)) {
            throw new RuntimeException('Bạn đang có kỳ trả góp quá hạn, vui lòng thanh toán trước khi đăng ký thêm.');
        }
    }

    /**
     * Tạo yêu cầu trả góp (chờ gian hàng duyệt) cho đơn hàng một gian hàng.
     */
    public function createRequest(Order $order, User $user, int $months): InstallmentPlan
    {
        $sellerOrders = $order->sellerOrders()->get();

        if ($sellerOrders->count() !== 1) {
            throw new RuntimeException('Trả góp chỉ áp dụng cho đơn hàng của một gian hàng. Hãy tách đơn theo từng gian hàng.');
        }

        $sellerOrder = $sellerOrders->first();
        $store = Store::findOrFail($sellerOrder->store_id);
        $principal = (float) $order->total_amount;

        $this->assertCanRequest($user, $store, $principal, $months);

        $option = collect($this->settingsFor($store)['options'])->firstWhere('months', $months);
        $quote = $this->quote($principal, $months, $option['monthly_rate']);
        $start = now('Asia/Ho_Chi_Minh')->startOfDay();

        return DB::transaction(function () use ($order, $user, $store, $sellerOrder, $quote, $start) {
            $plan = InstallmentPlan::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'store_id' => $store->id,
                'seller_order_id' => $sellerOrder->id,
                'principal' => $quote['principal'],
                'months' => $quote['months'],
                'monthly_rate' => $quote['monthly_rate'],
                'total_interest' => $quote['total_interest'],
                'total_payable' => $quote['total_payable'],
                'status' => 'pending_approval',
            ]);

            foreach ($quote['schedule'] as $row) {
                $plan->payments()->create([
                    'number' => $row['number'],
                    'amount' => $row['amount'],
                    'principal_part' => $row['principal_part'],
                    'interest_part' => $row['interest_part'],
                    'due_date' => $start->copy()->addMonthsNoOverflow($row['number'] - 1)->toDateString(),
                    'status' => 'pending',
                ]);
            }

            return $plan->load('payments');
        });
    }

    public function approve(InstallmentPlan $plan, ?string $note = null): InstallmentPlan
    {
        if ($plan->status !== 'pending_approval') {
            throw new RuntimeException('Yêu cầu trả góp này đã được xử lý rồi.');
        }

        $plan->update(['status' => 'active', 'decided_at' => now(), 'decision_note' => $note]);

        return $plan;
    }

    /**
     * Từ chối cho vay: đóng khoản vay. Người gọi chịu trách nhiệm huỷ đơn hàng đi kèm.
     */
    public function reject(InstallmentPlan $plan, ?string $note = null): InstallmentPlan
    {
        if ($plan->status !== 'pending_approval') {
            throw new RuntimeException('Yêu cầu trả góp này đã được xử lý rồi.');
        }

        DB::transaction(function () use ($plan, $note) {
            $plan->payments()->where('status', 'pending')->update(['status' => 'cancelled']);
            $plan->update(['status' => 'rejected', 'decided_at' => now(), 'decision_note' => $note]);
        });

        return $plan;
    }

    public function markPaid(InstallmentPayment $payment): InstallmentPayment
    {
        return DB::transaction(function () use ($payment) {
            $locked = InstallmentPayment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status !== 'pending') {
                return $locked;
            }

            $locked->update(['status' => 'paid', 'paid_at' => now()]);

            $plan = $locked->plan;
            $this->creditStoreWallet($locked, $plan);

            if (! $plan->payments()->where('status', '!=', 'paid')->exists()) {
                $plan->update(['status' => 'completed']);
            }

            return $locked;
        });
    }

    /**
     * Tiền khách trả mỗi kỳ về ví gian hàng, trừ hoa hồng sàn tính trên phần gốc của kỳ đó (lãi thuộc về gian hàng).
     */
    private function creditStoreWallet(InstallmentPayment $payment, InstallmentPlan $plan): void
    {
        $sellerOrder = $plan->sellerOrder;

        if (! $sellerOrder) {
            return;
        }

        $commission = round((float) $payment->principal_part * (float) $sellerOrder->commission_rate / 100, 2);
        $net = round((float) $payment->amount - $commission, 2);

        if ($net <= 0) {
            return;
        }

        $wallet = SellerWallet::firstOrCreate(['seller_profile_id' => $sellerOrder->seller_profile_id]);

        $this->walletService->creditAvailable(
            $wallet,
            $net,
            'installment_payment',
            $payment->id,
            "Khách trả góp đơn #{$plan->order_id} kỳ {$payment->number}/{$plan->months}",
        );
    }

    public function cancelForOrder(Order $order): void
    {
        $plan = InstallmentPlan::where('order_id', $order->id)->first();

        if (! $plan || ! in_array($plan->status, ['pending_approval', 'active'], true)) {
            return;
        }

        $plan->payments()->where('status', 'pending')->update(['status' => 'cancelled']);
        $plan->update(['status' => 'cancelled']);
    }

    /**
     * Thông tin giúp gian hàng cân nhắc cho vay.
     *
     * @return array<string, mixed>
     */
    public function customerProfile(User $customer, Store $store): array
    {
        $tierService = app(CustomerTierService::class);
        $totalSpent = $tierService->totalSpentForUser($customer->id);

        return [
            'tier' => $tierService->label($tierService->resolve($totalSpent)),
            'total_spent' => $totalSpent,
            'email_verified' => $customer->hasVerifiedEmail(),
            'member_since' => $customer->created_at,
            'completed_orders_here' => Order::where('user_id', $customer->id)
                ->whereHas('sellerOrders', fn ($q) => $q->where('store_id', $store->id)->where('status', 'completed'))
                ->count(),
            'completed_plans' => InstallmentPlan::where('user_id', $customer->id)->where('status', 'completed')->count(),
            'has_overdue' => $this->hasOverdue($customer),
            'outstanding' => $this->outstandingPrincipal($customer),
        ];
    }
}
