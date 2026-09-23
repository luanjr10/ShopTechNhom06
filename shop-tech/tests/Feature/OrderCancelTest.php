<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\Schema;

/**
 * Test OrderService::cancel() — quy tắc: khách chỉ hủy được khi TẤT CẢ
 * seller_order còn 'pending'; khi đã 'confirmed'/'shipping'/... thì không.
 * Cùng cách ly schema tối giản như các test khác (không dùng RefreshDatabase).
 */
beforeEach(function () {
    foreach (['order_items', 'seller_orders', 'orders', 'wallet_transactions', 'seller_wallets', 'stores', 'seller_profiles', 'products', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

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
        $table->decimal('price', 15, 2)->default(0);
        $table->decimal('discount_percent', 5, 2)->default(0);
        $table->integer('stock')->default(0);
        $table->integer('status')->default(1);
        $table->integer('category_id')->nullable();
        $table->foreignId('store_id')->nullable();
        $table->timestamps();
    });

    Schema::create('seller_profiles', function ($table) {
        $table->id();
        $table->foreignId('user_id')->unique();
        $table->string('display_name');
        $table->enum('status', ['active', 'suspended'])->default('active');
        $table->timestamps();
    });

    Schema::create('stores', function ($table) {
        $table->id();
        $table->foreignId('seller_profile_id');
        $table->string('name');
        $table->string('slug')->unique();
        $table->enum('status', ['active', 'inactive', 'pending'])->default('active');
        $table->timestamps();
    });

    Schema::create('seller_wallets', function ($table) {
        $table->id();
        $table->foreignId('seller_profile_id')->unique();
        $table->decimal('balance', 15, 2)->default(0);
        $table->decimal('pending_balance', 15, 2)->default(0);
        $table->decimal('withdrawable_balance', 15, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('wallet_transactions', function ($table) {
        $table->id();
        $table->foreignId('seller_wallet_id');
        $table->enum('type', ['hold', 'release', 'debit', 'credit', 'refund'])->index();
        $table->decimal('amount', 15, 2);
        $table->decimal('balance_after', 15, 2)->default(0);
        $table->string('reference_type')->nullable();
        $table->unsignedBigInteger('reference_id')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });

    Schema::create('orders', function ($table) {
        $table->id();
        $table->foreignId('user_id');
        $table->enum('status', ['pending', 'paid', 'completed', 'cancelled'])->default('pending');
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->decimal('shipping_fee', 15, 2)->default(0);
        $table->enum('payment_method', ['cod', 'momo', 'vnpay', 'onepay', 'sepay'])->default('cod');
        $table->string('discount_code')->nullable();
        $table->decimal('discount_amount', 15, 2)->default(0);
        $table->string('payment_ref')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->string('receiver_name');
        $table->string('receiver_phone');
        $table->string('shipping_address');
        $table->timestamps();
    });

    Schema::create('seller_orders', function ($table) {
        $table->id();
        $table->foreignId('order_id');
        $table->foreignId('store_id');
        $table->foreignId('seller_profile_id');
        $table->enum('status', ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'])->default('pending');
        $table->decimal('subtotal', 15, 2)->default(0);
        $table->decimal('commission_rate', 5, 2)->default(0);
        $table->decimal('commission_amount', 15, 2)->default(0);
        $table->decimal('seller_amount', 15, 2)->default(0);
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('order_items', function ($table) {
        $table->id();
        $table->foreignId('seller_order_id');
        $table->integer('product_id');
        $table->string('product_name');
        $table->string('sku')->nullable();
        $table->decimal('unit_price', 15, 2);
        $table->unsignedInteger('quantity');
        $table->decimal('line_total', 15, 2);
        $table->timestamps();
    });
});

afterEach(function () {
    foreach (['order_items', 'seller_orders', 'orders', 'wallet_transactions', 'seller_wallets', 'stores', 'seller_profiles', 'products', 'users'] as $table) {
        Schema::dropIfExists($table);
    }
});

function makeSellerStoreWithWallet(): array
{
    $sellerUser = User::factory()->create(['role' => 'seller']);
    $profile = SellerProfile::create(['user_id' => $sellerUser->id, 'display_name' => 'Seller', 'status' => 'active']);
    $store = Store::create(['seller_profile_id' => $profile->id, 'name' => 'Store', 'slug' => 'store-'.$profile->id, 'status' => 'active']);
    $wallet = SellerWallet::create(['seller_profile_id' => $profile->id]);

    return [$store, $wallet];
}

test('a pending order can be cancelled, stock/wallet untouched (never deducted/held)', function () {
    [$store, $wallet] = makeSellerStoreWithWallet();
    // Kho KHÔNG bị trừ và ví KHÔNG bị giữ lúc đặt hàng nữa (chỉ xảy ra lúc
    // seller bàn giao vận chuyển, xem SellerOrderService::handover) — 1 đơn
    // 'pending' chưa từng đụng tới stock/wallet, nên cancel() không có gì để
    // hoàn cả.
    $product = Product::create(['name' => 'X', 'price' => 100000, 'stock' => 10, 'status' => 1, 'store_id' => $store->id]);
    $customer = User::factory()->create(['role' => 'customer']);

    $order = Order::create([
        'user_id' => $customer->id, 'status' => 'pending', 'total_amount' => 200000,
        'receiver_name' => 'A', 'receiver_phone' => '090', 'shipping_address' => 'addr',
    ]);
    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id, 'store_id' => $store->id, 'seller_profile_id' => $store->seller_profile_id,
        'status' => 'pending', 'subtotal' => 200000, 'commission_rate' => 10,
    ]);
    $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => 'X', 'unit_price' => 100000, 'quantity' => 2, 'line_total' => 200000]);

    app(OrderService::class)->cancel($order);

    expect($order->refresh()->status)->toBe('cancelled');
    expect($sellerOrder->refresh()->status)->toBe('cancelled');
    expect($product->refresh()->stock)->toBe(10); // không đổi — chưa từng bị trừ
    expect((float) $wallet->refresh()->pending_balance)->toBe(0.0);
});

test('an order cannot be cancelled once any seller order has been confirmed', function () {
    [$store, $wallet] = makeSellerStoreWithWallet();
    $customer = User::factory()->create(['role' => 'customer']);

    $order = Order::create([
        'user_id' => $customer->id, 'status' => 'pending', 'total_amount' => 100000,
        'receiver_name' => 'A', 'receiver_phone' => '090', 'shipping_address' => 'addr',
    ]);
    SellerOrder::create([
        'order_id' => $order->id, 'store_id' => $store->id, 'seller_profile_id' => $store->seller_profile_id,
        'status' => 'confirmed', 'subtotal' => 100000, 'commission_rate' => 10,
    ]);

    expect(fn () => app(OrderService::class)->cancel($order))
        ->toThrow(RuntimeException::class);

    expect($order->refresh()->status)->toBe('pending');
});
