<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\User;
use App\Services\SellerOrderService;
use Illuminate\Support\Facades\Schema;

/**
 * Test quyết định 2026-09-19: kho chỉ bị trừ lúc seller bàn giao vận chuyển
 * (SellerOrderService::handover()), KHÔNG còn trừ lúc đặt hàng (OrderService::
 * place, xem OrderCancelTest). Hoàn kho chỉ xảy ra khi hủy đơn ĐÃ bàn giao.
 */
beforeEach(function () {
    foreach (['shipments', 'order_items', 'seller_orders', 'orders', 'wallet_transactions', 'seller_wallets', 'stores', 'seller_profiles', 'products', 'users'] as $table) {
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
        $table->timestamp('expected_delivery_time')->nullable();
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
        $table->enum('status', ['pending', 'confirmed', 'shipping', 'delivered', 'completed', 'cancelled'])->default('pending');
        $table->decimal('subtotal', 15, 2)->default(0);
        $table->decimal('shipping_fee', 15, 2)->default(0);
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

    Schema::create('shipments', function ($table) {
        $table->id();
        $table->foreignId('seller_order_id')->unique();
        $table->string('provider')->default('ghn');
        $table->string('tracking_number')->nullable();
        $table->string('client_order_code')->nullable();
        $table->unsignedTinyInteger('service_type_id')->nullable();
        $table->decimal('fee', 15, 2)->nullable();
        $table->unsignedInteger('cod_amount')->default(0);
        $table->timestamp('cod_transferred_at')->nullable();
        $table->unsignedInteger('weight_gram')->nullable();
        $table->unsignedInteger('length_cm')->nullable();
        $table->unsignedInteger('width_cm')->nullable();
        $table->unsignedInteger('height_cm')->nullable();
        $table->string('status')->nullable();
        $table->timestamp('expected_delivery_time')->nullable();
        $table->json('provider_response')->nullable();
        $table->timestamp('shipped_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    foreach (['shipments', 'order_items', 'seller_orders', 'orders', 'wallet_transactions', 'seller_wallets', 'stores', 'seller_profiles', 'products', 'users'] as $table) {
        Schema::dropIfExists($table);
    }
});

function makeConfirmedSellerOrder(int $stock = 10, int $quantity = 2): array
{
    $sellerUser = User::factory()->create(['role' => 'seller']);
    $profile = SellerProfile::create(['user_id' => $sellerUser->id, 'display_name' => 'Seller', 'status' => 'active']);
    $store = Store::create(['seller_profile_id' => $profile->id, 'name' => 'Store', 'slug' => 'store-'.$profile->id, 'status' => 'active']);
    $wallet = SellerWallet::create(['seller_profile_id' => $profile->id]);
    $product = Product::create(['name' => 'X', 'price' => 100000, 'stock' => $stock, 'status' => 1, 'store_id' => $store->id]);
    $customer = User::factory()->create(['role' => 'customer']);

    $order = Order::create([
        'user_id' => $customer->id, 'status' => 'pending', 'total_amount' => 200000,
        'shipping_fee' => 0, 'payment_method' => 'cod',
        'receiver_name' => 'A', 'receiver_phone' => '090', 'shipping_address' => 'addr',
    ]);
    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id, 'store_id' => $store->id, 'seller_profile_id' => $store->seller_profile_id,
        'status' => 'confirmed', 'subtotal' => 200000, 'shipping_fee' => 0, 'commission_rate' => 10,
    ]);
    $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => 'X', 'unit_price' => 100000, 'quantity' => $quantity, 'line_total' => 200000]);

    return [$sellerOrder, $product, $wallet];
}

test('handover deducts stock and holds the wallet only when seller hands off for shipping', function () {
    [$sellerOrder, $product, $wallet] = makeConfirmedSellerOrder(stock: 10, quantity: 2);

    expect($product->refresh()->stock)->toBe(10); // chưa đụng tới lúc còn 'confirmed'
    expect((float) $wallet->refresh()->pending_balance)->toBe(0.0);

    app(SellerOrderService::class)->handover($sellerOrder);

    expect($sellerOrder->refresh()->status)->toBe('shipping');
    expect($product->refresh()->stock)->toBe(8);
    expect((float) $wallet->refresh()->pending_balance)->toBe(180000.0); // 200k - 10% hoa hồng
});

test('handover fails and does not touch stock or wallet when not enough inventory', function () {
    [$sellerOrder, $product, $wallet] = makeConfirmedSellerOrder(stock: 1, quantity: 2);

    expect(fn () => app(SellerOrderService::class)->handover($sellerOrder))
        ->toThrow(RuntimeException::class);

    expect($sellerOrder->refresh()->status)->toBe('confirmed');
    expect($product->refresh()->stock)->toBe(1);
    expect((float) $wallet->refresh()->pending_balance)->toBe(0.0);
});

test('cancelling before handover does not restore stock or wallet (never deducted/held)', function () {
    [$sellerOrder, $product, $wallet] = makeConfirmedSellerOrder(stock: 10, quantity: 2);

    app(SellerOrderService::class)->updateStatus($sellerOrder, 'cancelled');

    expect($sellerOrder->refresh()->status)->toBe('cancelled');
    expect($product->refresh()->stock)->toBe(10);
    expect((float) $wallet->refresh()->pending_balance)->toBe(0.0);
});

test('cancelling after handover restores the stock and wallet hold that were deducted', function () {
    [$sellerOrder, $product, $wallet] = makeConfirmedSellerOrder(stock: 10, quantity: 2);

    app(SellerOrderService::class)->handover($sellerOrder);
    expect($product->refresh()->stock)->toBe(8);
    expect((float) $wallet->refresh()->pending_balance)->toBe(180000.0);

    app(SellerOrderService::class)->markCancelledByDriver($sellerOrder);

    expect($sellerOrder->refresh()->status)->toBe('cancelled');
    expect($product->refresh()->stock)->toBe(10);
    expect((float) $wallet->refresh()->pending_balance)->toBe(0.0);
});
