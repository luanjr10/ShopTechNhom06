<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Cùng giới hạn môi trường như SellerAdminIsolationTest: KHÔNG dùng RefreshDatabase
 * (migration cũ đọc Mongo trong up(), CLI test không có ext-mongodb) -> tự tạo
 * schema tối giản. CartController luôn query ProductSpecification/ProductImage
 * (Mongo) trong formatCart() dù sản phẩm không có variant/ảnh -> các case "happy
 * path" cần Mongo thật sẽ bị skip nếu CLI hiện tại thiếu ext-mongodb.
 */
beforeEach(function () {
    Schema::dropIfExists('cart_items');
    Schema::dropIfExists('carts');
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

    Schema::create('products', function ($table) {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->decimal('discount_percent', 5, 2)->default(0);
        $table->integer('stock')->default(0);
        $table->integer('status')->default(1);
        $table->integer('category_id')->nullable();
        $table->integer('brand_id')->nullable();
        $table->foreignId('store_id')->nullable();
        $table->timestamps();
    });

    Schema::create('carts', function ($table) {
        $table->id();
        $table->foreignId('user_id')->unique();
        $table->timestamps();
    });

    Schema::create('cart_items', function ($table) {
        $table->id();
        $table->foreignId('cart_id');
        $table->integer('product_id');
        $table->string('sku')->default('');
        $table->unsignedInteger('quantity');
        $table->timestamps();
        $table->unique(['cart_id', 'product_id', 'sku']);
    });
});

afterEach(function () {
    Schema::dropIfExists('cart_items');
    Schema::dropIfExists('carts');
    Schema::dropIfExists('products');
    Schema::dropIfExists('users');
});

function loginAsCustomer(): User
{
    $user = User::factory()->create(['role' => 'customer']);
    $token = Auth::guard('api')->login($user);
    test()->withHeader('Authorization', "Bearer {$token}");

    return $user;
}

// --- Bắt buộc đăng nhập (guest không có cart) ---

test('guest cannot view the cart', function () {
    test()->getJson('/api/cart')->assertStatus(401);
});

test('guest cannot add to the cart', function () {
    test()->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 1])->assertStatus(401);
});

// --- Không tìm thấy sản phẩm ---

test('adding a non existent product is rejected', function () {
    loginAsCustomer();

    test()->postJson('/api/cart/items', ['product_id' => 9999, 'quantity' => 1])
        ->assertStatus(422); // fails the exists:products,id validation rule
});

test('adding a hidden (status=0) product returns not found', function () {
    loginAsCustomer();
    $product = Product::create(['name' => 'Ẩn', 'price' => 100, 'stock' => 10, 'status' => 0]);

    test()->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertStatus(404);
});

// --- Chủ sở hữu: user A không đụng được cart_item của user B ---

test('a user cannot update or delete another users cart item', function () {
    $userA = loginAsCustomer();
    $product = Product::create(['name' => 'Sản phẩm', 'price' => 100, 'stock' => 10, 'status' => 1]);
    $cartA = Cart::create(['user_id' => $userA->id]);
    $itemA = $cartA->items()->create(['product_id' => $product->id, 'sku' => '', 'quantity' => 1]);

    loginAsCustomer(); // user B

    test()->putJson("/api/cart/items/{$itemA->id}", ['quantity' => 2])->assertStatus(404);
    test()->deleteJson("/api/cart/items/{$itemA->id}")->assertStatus(404);
});

// --- Happy path: cần Mongo thật (ProductSpecification/ProductImage) ---

test('a logged in customer can add a product to the cart and see it with correct subtotal', function () {
    loginAsCustomer();
    $product = Product::create(['name' => 'Chuột không dây', 'price' => 200000, 'discount_percent' => 10, 'stock' => 5, 'status' => 1]);

    test()->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
        ->assertStatus(201)
        ->assertJsonPath('data.total_quantity', 2)
        ->assertJsonPath('data.subtotal', 360000.0); // (200000 - 10%) * 2
})->skip(! extension_loaded('mongodb'), 'Cần ext-mongodb (ProductSpecification/ProductImage) để chạy được.');

test('adding beyond available stock is rejected without changing product stock', function () {
    loginAsCustomer();
    $product = Product::create(['name' => 'Bàn phím', 'price' => 500000, 'stock' => 3, 'status' => 1]);

    test()->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 5])
        ->assertStatus(422);

    expect($product->refresh()->stock)->toBe(3);
})->skip(! extension_loaded('mongodb'), 'Cần ext-mongodb (ProductSpecification/ProductImage) để chạy được.');
