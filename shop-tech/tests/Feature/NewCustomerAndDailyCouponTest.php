<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const COUPON_TABLES = ['coupon_redemptions', 'coupon_claims', 'coupons', 'orders', 'users'];

beforeEach(function () {
    foreach (COUPON_TABLES as $table) {
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

    Schema::create('orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('receiver_phone')->nullable();
        $table->string('status')->default('pending');
        $table->decimal('total_amount', 15, 2)->default(0);
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
    Carbon::setTestNow();
    foreach (COUPON_TABLES as $table) {
        Schema::dropIfExists($table);
    }
});

test('new-customer coupon works for the first order only', function () {
    Coupon::create(['code' => 'CHAOMUNG', 'type' => 'percent', 'value' => 10, 'max_discount' => 100000, 'new_customer_only' => true]);
    $user = User::factory()->create();
    $service = app(CouponService::class);

    expect($service->apply('CHAOMUNG', 500000, $user)['discount_amount'])->toBe(50000.0);

    DB::table('orders')->insert(['user_id' => $user->id, 'status' => 'cancelled']);
    expect($service->apply('CHAOMUNG', 500000, $user)['discount_amount'])->toBe(50000.0);

    DB::table('orders')->insert(['user_id' => $user->id, 'status' => 'pending']);
    expect(fn () => $service->apply('CHAOMUNG', 500000, $user))
        ->toThrow(RuntimeException::class, 'khách hàng mới');
});

test('new-customer coupon requires a logged-in user', function () {
    Coupon::create(['code' => 'CHAOMUNG', 'type' => 'fixed', 'value' => 10000, 'new_customer_only' => true]);

    expect(fn () => app(CouponService::class)->apply('CHAOMUNG', 500000))
        ->toThrow(RuntimeException::class, 'đăng nhập');
});

test('daily coupon only works on its weekday', function () {
    Coupon::create(['code' => 'THU3-GIAM5', 'type' => 'percent', 'value' => 5, 'weekday' => 2]);
    $user = User::factory()->create();
    $service = app(CouponService::class);

    Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00', 'Asia/Ho_Chi_Minh'));
    expect($service->apply('THU3-GIAM5', 400000, $user)['discount_amount'])->toBe(20000.0);

    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'Asia/Ho_Chi_Minh'));
    expect(fn () => $service->apply('THU3-GIAM5', 400000, $user))
        ->toThrow(RuntimeException::class, 'Thứ 3');
});

test('daily coupon can be used once per day by the same customer', function () {
    $coupon = Coupon::create(['code' => 'CN-GIAM8', 'type' => 'percent', 'value' => 8, 'weekday' => 0]);
    $user = User::factory()->create();
    $service = app(CouponService::class);

    Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00', 'Asia/Ho_Chi_Minh'));
    $service->apply('CN-GIAM8', 500000, $user);

    CouponRedemption::create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);
    expect(fn () => $service->apply('CN-GIAM8', 500000, $user))
        ->toThrow(RuntimeException::class, 'hôm nay');

    Carbon::setTestNow(Carbon::parse('2026-10-18 09:00:00', 'Asia/Ho_Chi_Minh'));
    expect($service->apply('CN-GIAM8', 500000, $user)['discount_amount'])->toBe(40000.0);
});

test('discount is capped by the platform commission of the order', function () {
    Coupon::create(['code' => 'GIAM100K', 'type' => 'fixed', 'value' => 100000]);
    $user = User::factory()->create();
    $service = app(CouponService::class);

    $capped = $service->apply('GIAM100K', 1000000, $user, 0, 40000);
    expect($capped['discount_amount'])->toBe(40000.0)
        ->and($capped['capped'])->toBeTrue()
        ->and($capped['original_discount'])->toBe(100000.0);

    $full = $service->apply('GIAM100K', 1000000, $user, 0, 150000);
    expect($full['discount_amount'])->toBe(100000.0)->and($full['capped'])->toBeFalse();

    expect($service->apply('GIAM100K', 1000000, $user, 0, 0)['discount_amount'])->toBe(0.0);
});

test('new-customer coupon needs a verified email', function () {
    Coupon::create(['code' => 'CHAOMUNG', 'type' => 'fixed', 'value' => 10000, 'new_customer_only' => true]);
    $user = User::factory()->unverified()->create();

    expect(fn () => app(CouponService::class)->apply('CHAOMUNG', 500000, $user))
        ->toThrow(RuntimeException::class, 'xác thực email');
});

test('new-customer coupon is refused for a phone number that already ordered', function () {
    Coupon::create(['code' => 'CHAOMUNG', 'type' => 'fixed', 'value' => 10000, 'new_customer_only' => true]);
    $oldAccount = User::factory()->create();
    DB::table('orders')->insert(['user_id' => $oldAccount->id, 'receiver_phone' => '0901234567', 'status' => 'completed']);
    $newAccount = User::factory()->create();
    $service = app(CouponService::class);

    expect(fn () => $service->apply('CHAOMUNG', 500000, $newAccount, 0, null, '0901234567'))
        ->toThrow(RuntimeException::class, 'khách hàng mới');

    expect($service->apply('CHAOMUNG', 500000, $newAccount, 0, null, '0999999999')['discount_amount'])->toBe(10000.0);
});

test('daily coupon stops when the daily limit is reached', function () {
    $coupon = Coupon::create(['code' => 'THU3-GIAM5', 'type' => 'percent', 'value' => 5, 'weekday' => 2, 'daily_limit' => 2]);
    Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00', 'Asia/Ho_Chi_Minh'));
    $service = app(CouponService::class);

    foreach (User::factory()->count(2)->create() as $other) {
        CouponRedemption::create(['coupon_id' => $coupon->id, 'user_id' => $other->id]);
    }

    expect(fn () => $service->apply('THU3-GIAM5', 400000, User::factory()->create()))
        ->toThrow(RuntimeException::class, 'hết lượt');
});
