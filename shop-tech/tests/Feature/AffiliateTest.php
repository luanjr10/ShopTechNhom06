<?php

use App\Models\AffiliateCommission;
use App\Models\Product;
use App\Models\ProductAffiliate;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const AFFILIATE_TABLES = ['order_items', 'product_affiliates', 'products', 'affiliate_withdrawals', 'affiliate_commissions', 'store_affiliate_settings', 'coupons', 'seller_orders', 'orders', 'stores', 'users'];

beforeEach(function () {
    foreach (AFFILIATE_TABLES as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('username')->nullable()->unique();
        $table->string('email')->unique();
        $table->string('role')->default('customer');
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('referral_code', 12)->nullable()->unique();
        $table->unsignedBigInteger('referred_by')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('stores', function ($table) {
        $table->id();
        $table->unsignedBigInteger('seller_profile_id')->default(1);
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('logo')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::create('store_affiliate_settings', function ($table) {
        $table->id();
        $table->unsignedBigInteger('store_id')->unique();
        $table->boolean('enabled')->default(false);
        $table->decimal('rate', 5, 2)->default(2);
        $table->unsignedSmallInteger('hold_days')->default(7);
        $table->unsignedInteger('min_withdrawal')->default(50000);
        $table->timestamps();
    });

    Schema::create('products', function ($table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('store_id')->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('product_affiliates', function ($table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->unique();
        $table->unsignedBigInteger('store_id');
        $table->decimal('rate', 5, 2);
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    Schema::create('orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->string('discount_code')->nullable();
        $table->decimal('xu_used', 15, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('coupons', function ($table) {
        $table->id();
        $table->string('code')->unique();
        $table->boolean('is_free_ship')->default(false);
        $table->unsignedBigInteger('store_id')->nullable();
        $table->timestamps();
    });

    Schema::create('seller_orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('store_id')->default(1);
        $table->unsignedBigInteger('seller_profile_id')->default(1);
        $table->string('status')->default('completed');
        $table->decimal('subtotal', 15, 2)->default(0);
        $table->decimal('shipping_fee', 15, 2)->default(0);
        $table->decimal('commission_rate', 5, 2)->default(0);
        $table->decimal('commission_amount', 15, 2)->default(0);
        $table->decimal('seller_amount', 15, 2)->default(0);
        $table->decimal('store_discount', 15, 2)->default(0);
        $table->decimal('store_shipping_subsidy', 15, 2)->default(0);
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('order_items', function ($table) {
        $table->id();
        $table->unsignedBigInteger('seller_order_id');
        $table->unsignedBigInteger('product_id');
        $table->string('product_name');
        $table->string('sku')->nullable();
        $table->decimal('unit_price', 15, 2);
        $table->unsignedInteger('quantity');
        $table->decimal('line_total', 15, 2);
        $table->unsignedBigInteger('affiliate_referrer_id')->nullable();
        $table->decimal('affiliate_rate', 5, 2)->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_commissions', function ($table) {
        $table->id();
        $table->unsignedBigInteger('referrer_id');
        $table->unsignedBigInteger('referred_user_id');
        $table->unsignedBigInteger('store_id')->nullable();
        $table->unsignedBigInteger('seller_order_id');
        $table->decimal('order_amount', 15, 2);
        $table->decimal('rate', 5, 2);
        $table->decimal('amount', 15, 2);
        $table->string('status')->default('pending');
        $table->timestamp('available_at');
        $table->timestamps();
        $table->unique(['seller_order_id', 'referrer_id']);
    });

    Schema::create('affiliate_withdrawals', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('store_id')->nullable();
        $table->decimal('amount', 15, 2);
        $table->string('bank_name');
        $table->string('bank_account');
        $table->string('account_holder');
        $table->string('status')->default('pending');
        $table->string('note')->nullable();
        $table->string('payout_reference')->nullable();
        $table->unsignedBigInteger('reviewed_by')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    foreach (AFFILIATE_TABLES as $table) {
        Schema::dropIfExists($table);
    }
});

function affiliateStore(bool $enabled = true, float $rate = 2, int $holdDays = 7, int $min = 50000): Store
{
    static $n = 0;
    $n++;

    $store = Store::create(['name' => 'Shop '.$n, 'slug' => 'shop-'.$n.'-'.uniqid()]);
    app(AffiliateService::class)->updateSettings($store, ['enabled' => $enabled, 'rate' => $rate, 'hold_days' => $holdDays, 'min_withdrawal' => $min]);

    return $store;
}

function affiliateProduct(Store $store, ?float $rate = 5, float $price = 1000000): Product
{
    static $n = 0;
    $n++;

    $product = Product::create(['name' => 'SP '.$n, 'store_id' => $store->id, 'price' => $price]);

    if ($rate !== null) {
        ProductAffiliate::create(['product_id' => $product->id, 'store_id' => $store->id, 'rate' => $rate]);
    }

    return $product;
}

function referrerWithCode(): User
{
    $user = User::factory()->create();
    app(AffiliateService::class)->ensureReferralCode($user);

    return $user->refresh();
}

/**
 * @param  list<array{product: Product, referrer: ?User, rate?: float, total?: float}>  $lines
 */
function makeCompletedSellerOrder(User $buyer, Store $store, array $lines, float $discount = 0, ?string $code = null): SellerOrder
{
    $orderId = DB::table('orders')->insertGetId(['user_id' => $buyer->id, 'discount_amount' => $discount, 'discount_code' => $code]);
    $subtotal = array_sum(array_map(fn ($line) => $line['total'] ?? 1000000, $lines));
    $id = DB::table('seller_orders')->insertGetId(['order_id' => $orderId, 'store_id' => $store->id, 'subtotal' => $subtotal]);

    foreach ($lines as $line) {
        DB::table('order_items')->insert([
            'seller_order_id' => $id,
            'product_id' => $line['product']->id,
            'product_name' => $line['product']->name,
            'unit_price' => $line['total'] ?? 1000000,
            'quantity' => 1,
            'line_total' => $line['total'] ?? 1000000,
            'affiliate_referrer_id' => $line['referrer']?->id,
            'affiliate_rate' => $line['referrer'] ? ($line['rate'] ?? 5) : null,
        ]);
    }

    return SellerOrder::find($id);
}

test('referral code is generated once and reused', function () {
    $user = User::factory()->create();
    $service = app(AffiliateService::class);

    $first = $service->ensureReferralCode($user);

    expect($first)->toHaveLength(8)
        ->and($service->ensureReferralCode($user->refresh()))->toBe($first);
});

test('a link only counts for a product with affiliate on, in a store running the program, and never for yourself', function () {
    $service = app(AffiliateService::class);
    $referrer = referrerWithCode();
    $buyer = User::factory()->create();
    $store = affiliateStore();
    $product = affiliateProduct($store, rate: 8);

    expect($service->attribute($buyer, $product, strtolower($referrer->referral_code)))
        ->toBe(['referrer_id' => $referrer->id, 'rate' => 8.0])
        ->and($service->attribute($referrer, $product, $referrer->referral_code))->toBeNull()
        ->and($service->attribute($buyer, $product, 'NOPE1234'))->toBeNull()
        ->and($service->attribute($buyer, $product, null))->toBeNull();

    $plain = affiliateProduct($store, rate: null);
    expect($service->attribute($buyer, $plain, $referrer->referral_code))->toBeNull();

    ProductAffiliate::where('product_id', $product->id)->update(['enabled' => false]);
    expect($service->attribute($buyer, $product, $referrer->referral_code))->toBeNull();

    ProductAffiliate::where('product_id', $product->id)->update(['enabled' => true]);
    $service->updateSettings($store, ['enabled' => false, 'rate' => 2, 'hold_days' => 7, 'min_withdrawal' => 50000]);
    expect($service->attribute($buyer, $product->refresh(), $referrer->referral_code))->toBeNull();
});

test('a seller enables affiliate with a rate for chosen products of their own store only', function () {
    $service = app(AffiliateService::class);
    $store = affiliateStore();
    $other = affiliateStore();
    $mine = affiliateProduct($store, rate: null);
    $foreign = affiliateProduct($other, rate: null);

    $count = $service->setProductsAffiliate($store, [$mine->id, $foreign->id], true, 7.5);

    expect($count)->toBe(1)
        ->and(ProductAffiliate::where('product_id', $mine->id)->value('rate'))->toBe('7.50')
        ->and(ProductAffiliate::where('product_id', $foreign->id)->exists())->toBeFalse();

    $service->setProductsAffiliate($store, [$mine->id], false, null);
    expect(ProductAffiliate::where('product_id', $mine->id)->first())
        ->enabled->toBeFalse()
        ->rate->toBe('7.50');
});

test('an order without referred items pays nobody', function () {
    $store = affiliateStore();
    $order = makeCompletedSellerOrder(User::factory()->create(), $store, [['product' => affiliateProduct($store), 'referrer' => null]]);

    expect(app(AffiliateService::class)->recordForCompletedOrder($order))->toBe([]);
});

test('commission uses the rate locked on each item, goes to the store, and is recorded once', function () {
    $store = affiliateStore();
    $referrer = referrerWithCode();
    $service = app(AffiliateService::class);
    $order = makeCompletedSellerOrder(User::factory()->create(), $store, [
        ['product' => affiliateProduct($store), 'referrer' => $referrer, 'rate' => 3, 'total' => 1000000],
        ['product' => affiliateProduct($store), 'referrer' => $referrer, 'rate' => 10, 'total' => 500000],
        ['product' => affiliateProduct($store), 'referrer' => null, 'total' => 2000000],
    ]);

    $created = $service->recordForCompletedOrder($order);

    expect($created)->toHaveCount(1)
        ->and((float) $created[0]->amount)->toBe(80000.0)
        ->and((float) $created[0]->order_amount)->toBe(1500000.0)
        ->and($created[0]->store_id)->toBe($store->id)
        ->and($service->recordForCompletedOrder($order))->toBe([])
        ->and(AffiliateCommission::count())->toBe(1);
});

test('different referrers on one order each get their own commission', function () {
    $store = affiliateStore();
    $a = referrerWithCode();
    $b = referrerWithCode();
    $order = makeCompletedSellerOrder(User::factory()->create(), $store, [
        ['product' => affiliateProduct($store), 'referrer' => $a, 'rate' => 5],
        ['product' => affiliateProduct($store), 'referrer' => $b, 'rate' => 10],
    ]);

    app(AffiliateService::class)->recordForCompletedOrder($order);

    expect((float) AffiliateCommission::where('referrer_id', $a->id)->value('amount'))->toBe(50000.0)
        ->and((float) AffiliateCommission::where('referrer_id', $b->id)->value('amount'))->toBe(100000.0);
});

test('commission is withdrawable only after the store hold period and withdrawals reserve the balance', function () {
    $store = affiliateStore(holdDays: 7, min: 10000);
    $referrer = referrerWithCode();
    $service = app(AffiliateService::class);
    $service->recordForCompletedOrder(makeCompletedSellerOrder(User::factory()->create(), $store, [['product' => affiliateProduct($store), 'referrer' => $referrer, 'rate' => 5]]));

    expect($service->balances($referrer, $store))->toMatchArray(['available' => 0.0, 'pending' => 50000.0]);

    $this->travel(8)->days();
    expect($service->balances($referrer, $store)['available'])->toBe(50000.0);

    $data = ['amount' => 30000, 'momo_phone' => '0912345678', 'account_holder' => 'A'];
    $withdrawal = $service->requestWithdrawal($referrer, $store, $data);

    expect($withdrawal->bank_name)->toBe('MoMo')
        ->and($withdrawal->bank_account)->toBe('0912345678')
        ->and($service->balances($referrer, $store)['available'])->toBe(20000.0);
    expect(fn () => $service->requestWithdrawal($referrer, $store, $data))->toThrow(RuntimeException::class);
});

test('balances and the minimum withdrawal are separate for every store', function () {
    $referrer = referrerWithCode();
    $storeA = affiliateStore(holdDays: 0, min: 10000);
    $storeB = affiliateStore(holdDays: 0, min: 100000);
    $service = app(AffiliateService::class);
    $buyer = User::factory()->create();

    $service->recordForCompletedOrder(makeCompletedSellerOrder($buyer, $storeA, [['product' => affiliateProduct($storeA), 'referrer' => $referrer, 'rate' => 5]]));
    $service->recordForCompletedOrder(makeCompletedSellerOrder($buyer, $storeB, [['product' => affiliateProduct($storeB), 'referrer' => $referrer, 'rate' => 10]]));

    expect($service->balances($referrer, $storeA)['available'])->toBe(50000.0)
        ->and($service->balances($referrer, $storeB)['available'])->toBe(100000.0);

    $data = ['amount' => 60000, 'momo_phone' => '0912345678', 'account_holder' => 'A'];
    expect(fn () => $service->requestWithdrawal($referrer, $storeA, $data))->toThrow(RuntimeException::class, 'vượt quá');
    expect(fn () => $service->requestWithdrawal($referrer, $storeB, $data))->toThrow(RuntimeException::class, 'tối thiểu');
    expect($service->requestWithdrawal($referrer, $storeB, ['amount' => 100000] + $data)->store_id)->toBe($storeB->id);
});

test('rejecting a withdrawal frees the balance and a MoMo payout settles it', function () {
    $store = affiliateStore(holdDays: 0, min: 10000);
    $referrer = referrerWithCode();
    $service = app(AffiliateService::class);
    $service->recordForCompletedOrder(makeCompletedSellerOrder(User::factory()->create(), $store, [['product' => affiliateProduct($store), 'referrer' => $referrer, 'rate' => 5]]));

    $data = ['amount' => 50000, 'momo_phone' => '0912345678', 'account_holder' => 'A'];
    $withdrawal = $service->requestWithdrawal($referrer, $store, $data);
    expect($service->balances($referrer, $store)['available'])->toBe(0.0);

    $service->reviewWithdrawal($withdrawal, 'rejected', 1, 'Sai thông tin');
    expect($service->balances($referrer, $store)['available'])->toBe(50000.0);
    expect(fn () => $service->reviewWithdrawal($withdrawal, 'approved', 1))->toThrow(RuntimeException::class);

    $second = $service->requestWithdrawal($referrer, $store, $data);
    $service->finalizePayout($second);

    expect($second->refresh()->status)->toBe('approved')
        ->and($service->balances($referrer, $store))->toMatchArray(['available' => 0.0, 'withdrawn' => 50000.0]);
});

test('cancelled commission no longer counts toward balances', function () {
    $store = affiliateStore();
    $referrer = referrerWithCode();
    $service = app(AffiliateService::class);
    $order = makeCompletedSellerOrder(User::factory()->create(), $store, [['product' => affiliateProduct($store), 'referrer' => $referrer]]);
    $service->recordForCompletedOrder($order);

    $service->cancelForSellerOrder($order->id);

    expect($service->balances($referrer, $store)['total_earned'])->toBe(0.0);
});

test('commission is calculated on the amount after a product voucher discount', function () {
    $store = affiliateStore();
    $referrer = referrerWithCode();
    DB::table('coupons')->insert(['code' => 'GIAM100K', 'is_free_ship' => false, 'store_id' => null]);

    $created = app(AffiliateService::class)->recordForCompletedOrder(
        makeCompletedSellerOrder(User::factory()->create(), $store, [['product' => affiliateProduct($store), 'referrer' => $referrer, 'rate' => 2]], 100000, 'GIAM100K')
    );

    expect((float) $created[0]->order_amount)->toBe(900000.0)
        ->and((float) $created[0]->amount)->toBe(18000.0);
});

test('a free-ship voucher does not reduce the commission base', function () {
    $store = affiliateStore();
    $referrer = referrerWithCode();
    DB::table('coupons')->insert(['code' => 'FREESHIP', 'is_free_ship' => true, 'store_id' => null]);

    $created = app(AffiliateService::class)->recordForCompletedOrder(
        makeCompletedSellerOrder(User::factory()->create(), $store, [['product' => affiliateProduct($store), 'referrer' => $referrer]], 30000, 'FREESHIP')
    );

    expect((float) $created[0]->order_amount)->toBe(1000000.0);
});

test('stores listed for a customer are the ones running affiliate or where they already earned', function () {
    $referrer = referrerWithCode();
    $running = affiliateStore();
    $stopped = affiliateStore(enabled: false);
    $service = app(AffiliateService::class);

    $service->recordForCompletedOrder(makeCompletedSellerOrder(User::factory()->create(), $running, [['product' => affiliateProduct($running), 'referrer' => $referrer]]));
    $names = collect($service->storesFor($referrer))->pluck('store.name')->all();

    expect($names)->toContain($running->name)->not->toContain($stopped->name);

    $service->updateSettings($running, ['enabled' => false, 'rate' => 2, 'hold_days' => 7, 'min_withdrawal' => 50000]);
    expect(collect($service->storesFor($referrer))->pluck('store.name')->all())->toContain($running->name);
});
