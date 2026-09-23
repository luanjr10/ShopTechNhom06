<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chỉ test tầng validate của POST /api/orders (không dựng schema đầy đủ
 * seller_profiles/stores/wallet cho happy-path — đã verify qua browser thật,
 * xem memory "cart-checkout"/"shipping-cod"). Validate chạy TRƯỚC khi đụng DB
 * nghiệp vụ nên không cần bảng products/orders thật ở đây.
 */
beforeEach(function () {
    Schema::dropIfExists('products');
    Schema::dropIfExists('users');

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('username')->nullable()->unique();
        $table->string('email')->unique();
        $table->enum('role', ['customer', 'seller', 'admin'])->default('customer');
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    // Chỉ cần tồn tại để pass rule exists:products,id — happy-path thật (trừ
    // stock/tạo SellerOrder) đã verify qua browser, không lặp lại ở đây.
    Schema::create('products', function ($table) {
        $table->id();
        $table->string('name')->default('x');
        $table->decimal('price', 15, 2)->default(0);
        $table->integer('stock')->default(0);
        $table->integer('status')->default(1);
        $table->timestamps();
    });
    DB::table('products')->insert(['id' => 1, 'name' => 'x', 'price' => 100, 'stock' => 10, 'status' => 1]);
});

afterEach(function () {
    Schema::dropIfExists('products');
    Schema::dropIfExists('users');
});

function loginAsPlacingCustomer(): User
{
    $user = User::factory()->create(['role' => 'customer']);
    $token = Auth::guard('api')->login($user);
    test()->withHeader('Authorization', "Bearer {$token}");

    return $user;
}

test('placing an order without province_code is rejected', function () {
    loginAsPlacingCustomer();

    test()->postJson('/api/orders', [
        'items' => [['product_id' => 1, 'quantity' => 1]],
        'receiver_name' => 'A',
        'receiver_phone' => '0900000000',
        'shipping_address' => 'somewhere',
        'payment_method' => 'cod',
    ])->assertStatus(422)->assertJsonValidationErrors(['province_code']);
});

test('placing an order with an invalid payment_method is rejected', function () {
    loginAsPlacingCustomer();

    test()->postJson('/api/orders', [
        'items' => [['product_id' => 1, 'quantity' => 1]],
        'receiver_name' => 'A',
        'receiver_phone' => '0900000000',
        'shipping_address' => 'somewhere',
        'province_code' => 1,
        'payment_method' => 'bitcoin',
    ])->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
});
