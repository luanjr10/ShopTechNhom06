<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\TradeInModel;
use App\Models\TradeInRequest;
use App\Models\User;
use App\Services\CouponService;
use App\Services\TradeInService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const TRADE_IN_TABLES = ['coupon_redemptions', 'coupon_claims', 'coupons', 'trade_in_requests', 'trade_in_models', 'seller_orders', 'orders', 'stores', 'users'];

beforeEach(function () {
    foreach (TRADE_IN_TABLES as $table) {
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
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('stores', function ($table) {
        $table->id();
        $table->unsignedBigInteger('seller_profile_id')->default(1);
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::create('seller_orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('store_id')->default(1);
        $table->decimal('subtotal', 15, 2)->default(0);
        $table->decimal('store_discount', 15, 2)->default(0);
        $table->decimal('store_shipping_subsidy', 15, 2)->default(0);
        $table->decimal('commission_rate', 5, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('discount_code')->nullable();
        $table->string('status')->default('pending');
        $table->string('receiver_phone')->nullable();
        $table->timestamps();
    });

    Schema::create('trade_in_models', function ($table) {
        $table->id();
        $table->unsignedBigInteger('store_id')->nullable();
        $table->string('category', 20);
        $table->string('brand', 60);
        $table->string('name', 150);
        $table->decimal('base_price', 15, 2);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('trade_in_requests', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('store_id')->nullable();
        $table->unsignedBigInteger('trade_in_model_id')->nullable();
        $table->string('model_name', 150);
        $table->string('condition', 20);
        $table->boolean('has_box')->default(false);
        $table->boolean('has_charger')->default(false);
        $table->text('description')->nullable();
        $table->json('images')->nullable();
        $table->decimal('estimated_price', 15, 2);
        $table->decimal('final_price', 15, 2)->nullable();
        $table->string('status')->default('pending');
        $table->string('admin_note')->nullable();
        $table->unsignedBigInteger('reviewed_by')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->string('coupon_code', 50)->nullable();
        $table->unsignedBigInteger('used_order_id')->nullable();
        $table->timestamps();
    });

    Schema::create('coupons', function ($table) {
        $table->id();
        $table->string('code')->unique();
        $table->string('title')->nullable();
        $table->string('description')->nullable();
        $table->string('type')->default('percent');
        $table->string('target_tier')->nullable();
        $table->boolean('is_free_ship')->default(false);
        $table->boolean('new_customer_only')->default(false);
        $table->unsignedTinyInteger('weekday')->nullable();
        $table->unsignedInteger('daily_limit')->nullable();
        $table->decimal('value', 15, 2)->default(0);
        $table->decimal('max_discount', 15, 2)->nullable();
        $table->decimal('min_order_amount', 15, 2)->default(0);
        $table->unsignedInteger('usage_limit')->nullable();
        $table->unsignedInteger('per_user_limit')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->timestamp('expires_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('trade_in_request_id')->nullable();
        $table->unsignedBigInteger('store_id')->nullable();
        $table->timestamps();
    });

    Schema::create('coupon_redemptions', function ($table) {
        $table->id();
        $table->unsignedBigInteger('coupon_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('order_id')->nullable();
        $table->timestamps();
    });

    Schema::create('coupon_claims', function ($table) {
        $table->id();
        $table->unsignedBigInteger('coupon_id');
        $table->unsignedBigInteger('user_id');
        $table->timestamp('claimed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    foreach (TRADE_IN_TABLES as $table) {
        Schema::dropIfExists($table);
    }
});

function tradeInStore(string $status = 'active'): Store
{
    static $counter = 0;
    $counter++;

    return Store::create(['seller_profile_id' => 1, 'name' => 'Store '.$counter, 'slug' => 'store-'.$counter.'-'.uniqid(), 'status' => $status]);
}

function sampleTradeInModel(float $price = 10000000, ?Store $store = null): TradeInModel
{
    $store ??= tradeInStore();

    return TradeInModel::create(['store_id' => $store->id, 'category' => 'phone', 'brand' => 'Apple', 'name' => 'iPhone 13', 'base_price' => $price]);
}

function submittedTradeIn(User $user, string $condition = 'good', ?TradeInModel $model = null): TradeInRequest
{
    $model ??= sampleTradeInModel();

    return app(TradeInService::class)->submit(
        $user,
        ['store_id' => $model->store_id, 'trade_in_model_id' => $model->id, 'condition' => $condition, 'has_box' => true, 'has_charger' => true],
        ['https://img/1.jpg'],
    );
}

test('estimate applies the condition multiplier and accessory bonuses and rounds to thousands', function () {
    $service = app(TradeInService::class);
    $model = sampleTradeInModel(9500000);

    expect($service->estimate($model, 'like_new', false, false)['estimated_price'])->toBe(9500000.0)
        ->and($service->estimate($model, 'good', false, false)['estimated_price'])->toBe(8075000.0)
        ->and($service->estimate($model, 'good', true, true)['estimated_price'])->toBe(8360000.0)
        ->and($service->estimate($model, 'poor', false, false)['estimated_price'])->toBe(3800000.0);

    expect(fn () => $service->estimate($model, 'broken', false, false))->toThrow(RuntimeException::class);
});

test('a customer can keep at most three pending requests per store', function () {
    $user = User::factory()->create();
    $model = sampleTradeInModel();

    foreach (range(1, 3) as $ignored) {
        submittedTradeIn($user, 'good', $model);
    }

    expect(fn () => submittedTradeIn($user, 'good', $model))->toThrow(RuntimeException::class, 'chờ duyệt');
    expect(submittedTradeIn($user)->status)->toBe('pending');
});

test('a request must target a model that the chosen store actually buys', function () {
    $user = User::factory()->create();
    $model = sampleTradeInModel();
    $otherStore = tradeInStore();
    $inactiveStore = tradeInStore('inactive');
    $inactiveModel = sampleTradeInModel(5000000, $inactiveStore);
    $service = app(TradeInService::class);

    expect(fn () => $service->submit($user, ['trade_in_model_id' => $model->id, 'condition' => 'good', 'store_id' => $otherStore->id], []))
        ->toThrow(RuntimeException::class, 'chưa thu mua');
    expect(fn () => $service->submit($user, ['trade_in_model_id' => $inactiveModel->id, 'condition' => 'good', 'store_id' => $inactiveStore->id], []))
        ->toThrow(RuntimeException::class, 'không nhận thu cũ');
});

test('approving creates a personal single-use voucher worth the final price', function () {
    $user = User::factory()->create();
    $request = submittedTradeIn($user);

    $approved = app(TradeInService::class)->approve($request, 99, 8000000, 'Máy đẹp');
    $coupon = Coupon::where('code', $approved->coupon_code)->first();

    expect($approved->status)->toBe('approved')
        ->and((float) $coupon->value)->toBe(8000000.0)
        ->and($coupon->user_id)->toBe($user->id)
        ->and($coupon->trade_in_request_id)->toBe($request->id)
        ->and($coupon->store_id)->toBe($request->store_id)
        ->and($coupon->usage_limit)->toBe(1);

    expect(fn () => app(TradeInService::class)->approve($approved, 99))->toThrow(RuntimeException::class, 'đã được xử lý');
});

test('the final price cannot exceed 150 percent of the estimate', function () {
    $request = submittedTradeIn(User::factory()->create());

    expect(fn () => app(TradeInService::class)->approve($request, 99, (float) $request->estimated_price * 2))
        ->toThrow(RuntimeException::class, 'không hợp lệ');
});

test('only the owner can use a trade-in voucher and the commission cap does not shrink it', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $approved = app(TradeInService::class)->approve(submittedTradeIn($owner), 99, 8000000);
    $coupons = app(CouponService::class);

    expect(fn () => $coupons->apply($approved->coupon_code, 20000000, $other))->toThrow(RuntimeException::class, 'không dành cho tài khoản');
    expect(fn () => $coupons->apply($approved->coupon_code, 20000000))->toThrow(RuntimeException::class, 'không dành cho tài khoản');

    $result = $coupons->apply($approved->coupon_code, 20000000, $owner, 0, 100000, null, [$approved->store_id => 20000000.0]);
    expect($result['discount_amount'])->toBe(8000000.0)->and($result['capped'])->toBeFalse();
});

test('a store voucher only counts the subtotal of its own store', function () {
    $owner = User::factory()->create();
    $approved = app(TradeInService::class)->approve(submittedTradeIn($owner), 99, 8000000);
    $coupons = app(CouponService::class);

    expect(fn () => $coupons->apply($approved->coupon_code, 20000000, $owner, 0, null, null, [9999 => 20000000.0]))
        ->toThrow(RuntimeException::class, 'chỉ áp dụng cho sản phẩm của gian hàng');

    expect(fn () => $coupons->apply($approved->coupon_code, 20000000, $owner, 0, null, null, [$approved->store_id => 5000000.0]))
        ->toThrow(RuntimeException::class, 'tối thiểu');

    $result = $coupons->apply($approved->coupon_code, 20000000, $owner, 0, null, null, [$approved->store_id => 9000000.0, 9999 => 11000000.0]);
    expect($result['discount_amount'])->toBe(8000000.0);
});

test('the store funded discount is deducted from the seller side only', function () {
    $buyer = User::factory()->create();
    $orderId = DB::table('orders')->insertGetId(['user_id' => $buyer->id, 'discount_code' => null]);
    $id = DB::table('seller_orders')->insertGetId(['order_id' => $orderId, 'subtotal' => 20000000, 'store_discount' => 8000000]);
    $sellerOrder = SellerOrder::find($id);

    expect($sellerOrder->storeBase())->toBe(12000000.0)
        ->and($sellerOrder->discountShare())->toBe(8000000.0)
        ->and($sellerOrder->netProductAmount())->toBe(12000000.0);
});

test('cancelling the order gives the trade-in voucher back', function () {
    $owner = User::factory()->create();
    $approved = app(TradeInService::class)->approve(submittedTradeIn($owner), 99, 8000000);
    $coupon = Coupon::where('code', $approved->coupon_code)->first();
    $orderId = DB::table('orders')->insertGetId(['user_id' => $owner->id, 'discount_code' => $coupon->code]);
    $order = Order::find($orderId);
    $service = app(TradeInService::class);

    CouponRedemption::create(['coupon_id' => $coupon->id, 'user_id' => $owner->id, 'order_id' => $order->id]);
    $coupon->increment('used_count');
    $service->markUsedByOrder($coupon, $order);
    expect($approved->refresh()->status)->toBe('used');

    $service->releaseForOrder($order);

    expect($approved->refresh()->status)->toBe('approved')
        ->and($coupon->refresh()->used_count)->toBe(0)
        ->and(CouponRedemption::count())->toBe(0);
});

test('a store free-ship voucher only discounts the shipping fee of its own store', function () {
    $store = tradeInStore();
    $other = tradeInStore();
    $user = User::factory()->create();
    Coupon::create(['code' => 'FREESHIP-SHOP', 'type' => 'free_ship', 'is_free_ship' => true, 'max_discount' => 50000, 'store_id' => $store->id]);
    $coupons = app(CouponService::class);

    $result = $coupons->apply('FREESHIP-SHOP', 5000000, $user, 80000, null, null, [$store->id => 3000000.0, $other->id => 2000000.0], [$store->id => 30000.0, $other->id => 50000.0]);

    expect($result['discount_amount'])->toBe(30000.0)
        ->and($result['discount_target'])->toBe('shipping');

    expect(fn () => $coupons->apply('FREESHIP-SHOP', 2000000, $user, 50000, null, null, [$other->id => 2000000.0], [$other->id => 50000.0]))
        ->toThrow(RuntimeException::class, 'chỉ áp dụng cho sản phẩm của gian hàng');
});

test('the seller net is goods after store discount minus commission minus the shipping subsidy', function () {
    $buyer = User::factory()->create();
    $orderId = DB::table('orders')->insertGetId(['user_id' => $buyer->id]);
    $id = DB::table('seller_orders')->insertGetId([
        'order_id' => $orderId,
        'subtotal' => 10000000,
        'store_discount' => 1000000,
        'store_shipping_subsidy' => 30000,
        'commission_rate' => 10,
    ]);

    // (10.000.000 - 1.000.000) - 10% hoa hồng - 30.000 hỗ trợ ship
    expect(SellerOrder::find($id)->sellerNet())->toBe(8070000.0);
});
