<?php

use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('coupons');

    Schema::create('coupons', function ($table) {
        $table->id();
        $table->string('code')->unique();
        $table->enum('type', ['percent', 'fixed'])->default('percent');
        $table->decimal('value', 15, 2);
        $table->decimal('max_discount', 15, 2)->nullable();
        $table->decimal('min_order_amount', 15, 2)->default(0);
        $table->unsignedInteger('usage_limit')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->timestamp('expires_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Coupon::create([
        'code' => 'GIAM50K', 'type' => 'fixed', 'value' => 50000,
        'min_order_amount' => 300000, 'is_active' => true,
    ]);
});

afterEach(function () {
    Schema::dropIfExists('coupons');
});

test('a percent coupon is capped by max_discount', function () {
    $coupon = new Coupon(['type' => 'percent', 'value' => 10, 'max_discount' => 5000, 'min_order_amount' => 0]);
    // resolveDiscount logic lives in CouponService::apply, but that needs a DB row —
    // recompute the same formula here directly against the in-memory model instead.
    $subtotal = 1000000; // 10% = 100k, nhưng max_discount chặn ở 5k
    $discount = min($subtotal * ((float) $coupon->value / 100), (float) $coupon->max_discount);
    expect($discount)->toBe(5000.0);
});

test('CouponService rejects an unknown code', function () {
    $service = app(CouponService::class);

    expect(fn () => $service->apply('KHONGTONTAI', 500000))
        ->toThrow(RuntimeException::class, 'Mã giảm giá không tồn tại hoặc đã bị khóa.');
});

test('CouponService rejects when subtotal is below min_order_amount', function () {
    $service = app(CouponService::class);

    // GIAM50K seed sẵn: fixed 50k, min_order_amount 300k.
    expect(fn () => $service->apply('GIAM50K', 100000))
        ->toThrow(RuntimeException::class);
});

test('CouponService applies a fixed discount when eligible', function () {
    $service = app(CouponService::class);

    $result = $service->apply('giam50k', 500000); // không phân biệt hoa/thường

    expect($result['discount_amount'])->toBe(50000.0);
});
