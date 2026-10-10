<?php

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\XuService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const XU_TABLES = ['daily_checkins', 'xu_transactions', 'coupons', 'seller_orders', 'orders', 'users'];

beforeEach(function () {
    foreach (XU_TABLES as $table) {
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
        $table->unsignedInteger('xu_balance')->default(0);
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->string('discount_code')->nullable();
        $table->unsignedInteger('xu_used')->default(0);
        $table->timestamps();
    });

    Schema::create('coupons', function ($table) {
        $table->id();
        $table->string('code')->unique();
        $table->boolean('is_free_ship')->default(false);
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
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('xu_transactions', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('type', 30);
        $table->integer('amount');
        $table->unsignedInteger('balance_after');
        $table->string('reference_type', 30)->nullable();
        $table->unsignedBigInteger('reference_id')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
        $table->unique(['user_id', 'type', 'reference_type', 'reference_id'], 'xu_transactions_unique_ref');
    });

    Schema::create('daily_checkins', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->date('checkin_date');
        $table->unsignedInteger('streak');
        $table->unsignedInteger('xu_earned');
        $table->timestamps();
        $table->unique(['user_id', 'checkin_date']);
    });
});

afterEach(function () {
    Carbon::setTestNow();
    foreach (XU_TABLES as $table) {
        Schema::dropIfExists($table);
    }
});

function xuSellerOrder(User $buyer, float $subtotal, float $discount = 0, ?string $code = null, int $xuUsed = 0): SellerOrder
{
    $orderId = DB::table('orders')->insertGetId(['user_id' => $buyer->id, 'discount_amount' => $discount, 'discount_code' => $code, 'xu_used' => $xuUsed]);
    $id = DB::table('seller_orders')->insertGetId(['order_id' => $orderId, 'subtotal' => $subtotal]);

    return SellerOrder::find($id);
}

test('daily check-in pays the base reward and refuses a second check-in the same day', function () {
    $user = User::factory()->create();
    $service = app(XuService::class);
    Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00', 'Asia/Ho_Chi_Minh'));

    expect($service->checkIn($user))->toMatchArray(['xu' => 100, 'streak' => 1, 'bonus' => false]);
    expect($user->refresh()->xu_balance)->toBe(100);

    expect(fn () => $service->checkIn($user))->toThrow(RuntimeException::class, 'đã điểm danh');
});

test('a seven day streak earns the bonus and a missed day resets it', function () {
    $user = User::factory()->create();
    $service = app(XuService::class);

    foreach (range(1, 7) as $day) {
        Carbon::setTestNow(Carbon::parse("2026-10-{$day} 08:00:00", 'Asia/Ho_Chi_Minh'));
        $result = $service->checkIn($user);
    }

    expect($result)->toMatchArray(['xu' => 600, 'streak' => 7, 'bonus' => true]);

    Carbon::setTestNow(Carbon::parse('2026-10-09 08:00:00', 'Asia/Ho_Chi_Minh'));
    expect($service->checkIn($user)['streak'])->toBe(1);
});

test('completed order earns 1 percent of the amount actually paid, once', function () {
    $buyer = User::factory()->create();
    DB::table('coupons')->insert(['code' => 'GIAM100K', 'is_free_ship' => false]);
    $sellerOrder = xuSellerOrder($buyer, 1000000, 100000, 'GIAM100K');
    $service = app(XuService::class);

    $service->earnForSellerOrder($sellerOrder);
    $service->earnForSellerOrder($sellerOrder);

    expect($buyer->refresh()->xu_balance)->toBe(9000);
});

test('reversing an earn takes the xu back but never below zero', function () {
    $buyer = User::factory()->create();
    $sellerOrder = xuSellerOrder($buyer, 1000000);
    $service = app(XuService::class);

    $service->earnForSellerOrder($sellerOrder);
    DB::table('users')->where('id', $buyer->id)->update(['xu_balance' => 4000]);

    $service->reverseEarnForSellerOrder($sellerOrder->id);
    $service->reverseEarnForSellerOrder($sellerOrder->id);

    expect($buyer->refresh()->xu_balance)->toBe(0);
});

test('max redeemable is limited by balance, half the order and the commission headroom', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['xu_balance' => 500000]);
    $service = app(XuService::class);

    expect($service->maxRedeemable($user, 1000000, 900000))->toBe(500000);
    expect($service->maxRedeemable($user, 600000, 900000))->toBe(300000);
    expect($service->maxRedeemable($user, 1000000, 40000))->toBe(40000);
    expect($service->maxRedeemable($user, 1000000, -5))->toBe(0);
});

test('spending deducts the balance, a cancelled order refunds it once, and overspending fails', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['xu_balance' => 5000]);
    $orderId = DB::table('orders')->insertGetId(['user_id' => $user->id, 'xu_used' => 3000]);
    $order = Order::find($orderId);
    $service = app(XuService::class);

    $service->spendOnOrder($user, $order, 3000);
    expect($user->refresh()->xu_balance)->toBe(2000);

    expect(fn () => $service->spendOnOrder($user, $order, 3000))->toThrow(RuntimeException::class, 'không đủ');

    $service->refundOrder($order);
    $service->refundOrder($order);
    expect($user->refresh()->xu_balance)->toBe(5000);
});
