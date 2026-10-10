<?php

namespace App\Services;

use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\Product;
use App\Models\ProductAffiliate;
use App\Models\ProductImage;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\StoreAffiliateSetting;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Affiliate do từng gian hàng tự bật và tự cấu hình: gian hàng đặt tỷ lệ hoa hồng, thời gian đối soát,
 * mức rút tối thiểu, và tự duyệt/chi trả các yêu cầu rút của người giới thiệu.
 */
class AffiliateService
{
    /**
     * @return array{enabled: bool, rate: float, hold_days: int, min_withdrawal: int}
     */
    public function settingsFor(Store|int $store): array
    {
        $storeId = $store instanceof Store ? $store->id : $store;
        $setting = StoreAffiliateSetting::where('store_id', $storeId)->first();

        return [
            'enabled' => (bool) ($setting?->enabled ?? false),
            'rate' => (float) ($setting?->rate ?? StoreAffiliateSetting::DEFAULT_RATE),
            'hold_days' => (int) ($setting?->hold_days ?? StoreAffiliateSetting::DEFAULT_HOLD_DAYS),
            'min_withdrawal' => (int) ($setting?->min_withdrawal ?? StoreAffiliateSetting::DEFAULT_MIN_WITHDRAWAL),
        ];
    }

    /**
     * @param  array{enabled: bool, rate: numeric, hold_days: int, min_withdrawal: int}  $values
     */
    public function updateSettings(Store $store, array $values): StoreAffiliateSetting
    {
        return StoreAffiliateSetting::updateOrCreate(['store_id' => $store->id], [
            'enabled' => (bool) $values['enabled'],
            'rate' => $values['rate'],
            'hold_days' => $values['hold_days'],
            'min_withdrawal' => $values['min_withdrawal'],
        ]);
    }

    public function ensureReferralCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    /**
     * Ghi nhận người giới thiệu cho một dòng hàng khi đặt đơn: chỉ hợp lệ nếu sản phẩm đang bật affiliate,
     * gian hàng đang bật chương trình, và người giới thiệu không phải chính người mua.
     *
     * @return array{referrer_id: int, rate: float}|null
     */
    public function attribute(User $buyer, Product $product, ?string $code): ?array
    {
        if (! $code || ! $product->store_id) {
            return null;
        }

        $referrer = User::where('referral_code', strtoupper(trim($code)))->first(['id']);

        if (! $referrer || $referrer->id === $buyer->id) {
            return null;
        }

        if (! $this->settingsFor((int) $product->store_id)['enabled']) {
            return null;
        }

        $affiliate = ProductAffiliate::where('product_id', $product->id)->where('enabled', true)->first();

        if (! $affiliate) {
            return null;
        }

        return ['referrer_id' => $referrer->id, 'rate' => (float) $affiliate->rate];
    }

    /**
     * Bật/tắt affiliate và đặt tỷ lệ hoa hồng cho nhiều sản phẩm của gian hàng cùng lúc.
     *
     * @param  list<int>  $productIds
     */
    public function setProductsAffiliate(Store $store, array $productIds, bool $enabled, ?float $rate): int
    {
        $ids = Product::where('store_id', $store->id)->whereIn('id', $productIds)->pluck('id');

        foreach ($ids as $productId) {
            $existing = ProductAffiliate::where('product_id', $productId)->first();

            ProductAffiliate::updateOrCreate(['product_id' => $productId], [
                'store_id' => $store->id,
                'enabled' => $enabled,
                'rate' => $rate ?? ($existing?->rate ?? $this->settingsFor($store)['rate']),
            ]);
        }

        return $ids->count();
    }

    /**
     * Sản phẩm đang chạy affiliate, kèm ảnh và tiền hoa hồng ước tính trên mỗi sản phẩm.
     *
     * @param  array{search?: string, store_id?: int|string|null, per_page?: int|string|null}  $filters
     */
    public function catalog(array $filters): LengthAwarePaginator
    {
        $enabledStores = StoreAffiliateSetting::where('enabled', true)->pluck('store_id');

        $query = Product::query()
            ->join('product_affiliates', 'product_affiliates.product_id', '=', 'products.id')
            ->join('stores', 'stores.id', '=', 'products.store_id')
            ->where('product_affiliates.enabled', true)
            ->whereIn('products.store_id', $enabledStores)
            ->where('stores.status', 'active')
            ->where('products.status', 1)
            ->select('products.*', 'product_affiliates.rate as affiliate_rate', 'stores.name as store_name', 'stores.slug as store_slug')
            ->orderByDesc('product_affiliates.rate')
            ->orderByDesc('products.id');

        if (! empty($filters['search'])) {
            $query->where('products.name', 'like', '%'.trim((string) $filters['search']).'%');
        }

        if (! empty($filters['store_id'])) {
            $query->where('products.store_id', (int) $filters['store_id']);
        }

        $paginator = $query->paginate(min((int) ($filters['per_page'] ?? 12), 48) ?: 12);

        $images = ProductImage::whereIn('productId', collect($paginator->items())->pluck('id')->all())
            ->get()->keyBy('productId');

        $paginator->through(function (Product $product) use ($images) {
            $price = (float) $product->price * (1 - (float) $product->discount_percent / 100);
            $rate = (float) $product->affiliate_rate;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => round($price, 2),
                'original_price' => (float) $product->price,
                'discount_percent' => (float) $product->discount_percent,
                'thumbnail' => ($images->get($product->id)?->images ?? [])[0] ?? null,
                'store' => ['id' => $product->store_id, 'name' => $product->store_name, 'slug' => $product->store_slug],
                'rate' => $rate,
                'commission_estimate' => round($price * $rate / 100, 2),
            ];
        });

        return $paginator;
    }

    /**
     * Hoa hồng phát sinh khi đơn con hoàn thành: mỗi người giới thiệu một khoản, tính theo tỷ lệ đã chốt
     * trên từng dòng hàng và phần giá trị hàng thực trả (đã trừ giảm giá).
     *
     * @return list<AffiliateCommission>
     */
    public function recordForCompletedOrder(SellerOrder $sellerOrder): array
    {
        $buyerId = (int) $sellerOrder->order->user_id;
        $items = $sellerOrder->items()->whereNotNull('affiliate_referrer_id')->get();

        if ($items->isEmpty()) {
            return [];
        }

        $subtotal = (float) $sellerOrder->subtotal;
        $factor = $subtotal > 0 ? $sellerOrder->netProductAmount() / $subtotal : 0.0;
        $holdDays = $this->settingsFor((int) $sellerOrder->store_id)['hold_days'];
        $created = [];

        foreach ($items->groupBy('affiliate_referrer_id') as $referrerId => $lines) {
            if ((int) $referrerId === $buyerId) {
                continue;
            }

            if (AffiliateCommission::where('seller_order_id', $sellerOrder->id)->where('referrer_id', $referrerId)->exists()) {
                continue;
            }

            $base = round($lines->sum(fn ($line) => (float) $line->line_total) * $factor, 2);
            $amount = round($lines->sum(fn ($line) => (float) $line->line_total * $factor * (float) $line->affiliate_rate / 100), 2);

            if ($amount <= 0) {
                continue;
            }

            $created[] = AffiliateCommission::create([
                'referrer_id' => $referrerId,
                'referred_user_id' => $buyerId,
                'store_id' => $sellerOrder->store_id,
                'seller_order_id' => $sellerOrder->id,
                'order_amount' => $base,
                'rate' => round($amount / $base * 100, 2),
                'amount' => $amount,
                'status' => 'pending',
                'available_at' => now()->addDays($holdDays),
            ]);
        }

        return $created;
    }

    public function cancelForSellerOrder(int $sellerOrderId): void
    {
        AffiliateCommission::where('seller_order_id', $sellerOrderId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Số dư affiliate của một người tại một gian hàng (mỗi gian hàng tự chi trả phần của mình).
     *
     * @return array{available: float, pending: float, withdrawn: float, withdrawing: float, total_earned: float}
     */
    public function balances(User $user, Store|int $store): array
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        $commissions = AffiliateCommission::where('referrer_id', $user->id)->where('store_id', $storeId)->where('status', 'pending');

        $matured = (float) (clone $commissions)->where('available_at', '<=', now())->sum('amount');
        $waiting = (float) (clone $commissions)->where('available_at', '>', now())->sum('amount');

        $withdrawals = AffiliateWithdrawal::where('user_id', $user->id)->where('store_id', $storeId);
        $withdrawn = (float) (clone $withdrawals)->where('status', 'approved')->sum('amount');
        $withdrawing = (float) (clone $withdrawals)->where('status', 'pending')->sum('amount');

        return [
            'available' => round($matured - $withdrawn - $withdrawing, 2),
            'pending' => $waiting,
            'withdrawn' => $withdrawn,
            'withdrawing' => $withdrawing,
            'total_earned' => $matured + $waiting,
        ];
    }

    /**
     * Các gian hàng đang chạy affiliate hoặc người dùng đã có hoa hồng/yêu cầu rút ở đó.
     *
     * @return list<array<string, mixed>>
     */
    public function storesFor(User $user): array
    {
        $historyStoreIds = AffiliateCommission::where('referrer_id', $user->id)->pluck('store_id')
            ->merge(AffiliateWithdrawal::where('user_id', $user->id)->pluck('store_id'))
            ->filter()->unique();

        $enabledStoreIds = StoreAffiliateSetting::where('enabled', true)->pluck('store_id');

        return Store::whereIn('id', $historyStoreIds->merge($enabledStoreIds)->unique()->values())
            ->where('status', 'active')
            ->get(['id', 'name', 'slug', 'logo'])
            ->map(fn (Store $store) => [
                'store' => $store,
                'settings' => $this->settingsFor($store),
                'balances' => $this->balances($user, $store),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{amount: numeric, momo_phone: string, account_holder: string}  $data
     */
    public function requestWithdrawal(User $user, Store $store, array $data): AffiliateWithdrawal
    {
        return DB::transaction(function () use ($user, $store, $data) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $amount = (float) $data['amount'];
            $min = $this->settingsFor($store)['min_withdrawal'];

            if ($amount < $min) {
                throw new RuntimeException('Số tiền rút tối thiểu tại gian hàng này là '.number_format($min, 0, ',', '.').'đ');
            }

            if ($amount > $this->balances($user, $store)['available']) {
                throw new RuntimeException('Số tiền rút vượt quá số dư có thể rút tại gian hàng này');
            }

            return AffiliateWithdrawal::create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'amount' => $amount,
                'bank_name' => 'MoMo',
                'bank_account' => $data['momo_phone'],
                'account_holder' => $data['account_holder'],
                'status' => 'pending',
            ]);
        });
    }

    public function finalizePayout(AffiliateWithdrawal $withdrawal): void
    {
        if ($withdrawal->status !== 'pending') {
            return;
        }

        $withdrawal->update([
            'status' => 'approved',
            'note' => 'Đã chi trả qua MoMo',
            'reviewed_at' => now(),
        ]);
    }

    public function reviewWithdrawal(AffiliateWithdrawal $withdrawal, string $status, int $reviewerId, ?string $note = null): AffiliateWithdrawal
    {
        if ($withdrawal->status !== 'pending') {
            throw new RuntimeException('Yêu cầu này đã được xử lý rồi');
        }

        $withdrawal->update([
            'status' => $status,
            'note' => $note,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return $withdrawal;
    }
}
