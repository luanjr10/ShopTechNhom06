<?php

use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * KHÔNG dùng RefreshDatabase ở đây: nó chạy lại toàn bộ migrations trên sqlite
 * :memory:, và migration cũ 2026_09_12_160000_fix_product_images_string_field
 * đọc model Mongo (ProductImage::all()) ngay trong up() — CLI test này không có
 * ext-mongodb (xem tests/TestCase.php) nên migrate thật sẽ luôn throw. Test này
 * chỉ cần users/seller_profiles/stores nên tự tạo schema tối giản, tách biệt
 * khỏi bộ migrations thật.
 */
beforeEach(function () {
    Schema::dropIfExists('stores');
    Schema::dropIfExists('seller_profiles');
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

    Schema::create('seller_profiles', function ($table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
        $table->string('display_name');
        $table->string('phone')->nullable();
        $table->enum('status', ['active', 'suspended'])->default('active');
        $table->timestamps();
    });

    Schema::create('stores', function ($table) {
        $table->id();
        $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('logo')->nullable();
        $table->text('description')->nullable();
        $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
        $table->timestamps();
    });

    Schema::create('products', function ($table) {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name');
        $table->integer('stock')->default(0);
        $table->integer('status')->default(1);
        $table->foreignId('store_id')->nullable();
        $table->timestamps();
    });

    Schema::create('seller_orders', function ($table) {
        $table->id();
        $table->foreignId('store_id');
        $table->foreignId('seller_profile_id');
        $table->string('status')->default('pending');
        $table->decimal('subtotal', 15, 2)->default(0);
        $table->decimal('commission_amount', 15, 2)->default(0);
        $table->decimal('seller_amount', 15, 2)->default(0);
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('seller_orders');
    Schema::dropIfExists('products');
    Schema::dropIfExists('stores');
    Schema::dropIfExists('seller_profiles');
    Schema::dropIfExists('users');
});

function actingAsRole(string $role): User
{
    $user = User::factory()->create(['role' => $role]);
    $token = Auth::guard('api')->login($user);
    test()->withHeader('Authorization', "Bearer {$token}");

    return $user;
}

function makeSellerWithStore(string $status = 'active'): array
{
    $user = User::factory()->create(['role' => 'seller']);
    $profile = SellerProfile::create([
        'user_id' => $user->id,
        'display_name' => $user->name,
        'status' => $status,
    ]);
    $store = Store::create([
        'seller_profile_id' => $profile->id,
        'name' => 'Test Store '.$profile->id,
        'slug' => 'test-store-'.$profile->id,
        'status' => 'active',
    ]);

    return [$user, $profile, $store];
}

// --- Unauthenticated cannot mutate catalog (the Postman scenario) ---

test('unauthenticated cannot create a product', function () {
    $this->postJson('/api/products', ['name' => 'hack'])->assertStatus(401);
});

test('unauthenticated cannot update or delete a product', function () {
    $this->putJson('/api/products/1', ['name' => 'hack'])->assertStatus(401);
    $this->deleteJson('/api/products/1')->assertStatus(401);
});

test('unauthenticated cannot create a category or brand', function () {
    $this->postJson('/api/categories', ['name' => 'hack'])->assertStatus(401);
    $this->postJson('/api/brands', ['name' => 'hack'])->assertStatus(401);
});

test('a logged in customer cannot create a product', function () {
    actingAsRole('customer');

    $this->postJson('/api/products', ['name' => 'hack'])->assertStatus(403);
});

// --- Seller cannot reach admin routes ---

test('seller cannot access admin seller-applications route', function () {
    actingAsRole('seller');

    $this->getJson('/api/admin/seller-applications')->assertStatus(403);
});

test('seller cannot list or approve withdrawals via admin route', function () {
    actingAsRole('seller');

    $this->getJson('/api/admin/withdrawals')->assertStatus(403);
});

test('admin cannot use seller-only routes (not a seller profile)', function () {
    actingAsRole('admin');

    $this->getJson('/api/seller/stores')->assertStatus(403);
});

// --- Seller A cannot touch seller B's store ---

test('a seller cannot view or update another sellers store', function () {
    [, , $storeA] = makeSellerWithStore();
    [$userB] = makeSellerWithStore();

    Auth::guard('api')->login($userB);
    $token = Auth::guard('api')->login($userB);
    $this->withHeader('Authorization', "Bearer {$token}");

    $this->getJson("/api/seller/stores/{$storeA->id}")->assertStatus(403);
    $this->patchJson("/api/seller/stores/{$storeA->id}", ['name' => 'stolen'])->assertStatus(403);
});

test('a seller cannot list products or inventory of another sellers store', function () {
    [, , $storeA] = makeSellerWithStore();
    [$userB] = makeSellerWithStore();

    $token = Auth::guard('api')->login($userB);
    $this->withHeader('Authorization', "Bearer {$token}");

    $this->getJson("/api/seller/stores/{$storeA->id}/products")->assertStatus(403);
    $this->getJson("/api/seller/stores/{$storeA->id}/inventory")->assertStatus(403);
    $this->getJson("/api/seller/stores/{$storeA->id}/revenue")->assertStatus(403);
    $this->getJson("/api/seller/stores/{$storeA->id}/orders")->assertStatus(403);
});

test('a suspended seller is blocked from the seller center', function () {
    [$user] = makeSellerWithStore(status: 'suspended');

    $token = Auth::guard('api')->login($user);
    $this->withHeader('Authorization', "Bearer {$token}");

    $this->getJson('/api/seller/stores')->assertStatus(403);
});

test('a seller can access their own store scoped routes', function () {
    [$user, , $store] = makeSellerWithStore();

    $token = Auth::guard('api')->login($user);
    $this->withHeader('Authorization', "Bearer {$token}");

    $this->getJson("/api/seller/stores/{$store->id}")->assertStatus(200);
    $this->getJson("/api/seller/stores/{$store->id}/inventory")->assertStatus(200);
    $this->getJson("/api/seller/stores/{$store->id}/revenue")->assertStatus(200);
});
