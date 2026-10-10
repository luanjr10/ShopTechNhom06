<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Notifications\PriceDropNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

const WISHLIST_TABLES = ['wishlists', 'products', 'users'];

beforeEach(function () {
    foreach (WISHLIST_TABLES as $table) {
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

    Schema::create('products', function ($table) {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->decimal('price', 15, 2)->default(0);
        $table->decimal('discount_percent', 5, 2)->default(0);
        $table->integer('stock')->default(0);
        $table->integer('status')->default(1);
        $table->timestamps();
    });

    Schema::create('wishlists', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('product_id');
        $table->decimal('target_price', 15, 2)->nullable();
        $table->decimal('last_notified_price', 15, 2)->nullable();
        $table->timestamps();
        $table->unique(['user_id', 'product_id']);
    });

    Notification::fake();
});

afterEach(function () {
    foreach (WISHLIST_TABLES as $table) {
        Schema::dropIfExists($table);
    }
});

function watchedProduct(?float $targetPrice = null, float $price = 1000000): array
{
    $user = User::factory()->create();
    $product = Product::create(['name' => 'Laptop X', 'price' => $price, 'stock' => 5, 'status' => 1]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id, 'target_price' => $targetPrice]);

    return [$user, $product];
}

test('watcher is notified when the product price drops', function () {
    [$user, $product] = watchedProduct();

    $product->update(['price' => 900000]);

    Notification::assertSentTo($user, PriceDropNotification::class, fn ($n) => $n->oldPrice === 1000000.0 && $n->newPrice === 900000.0);
});

test('a discount percent counts as a price drop', function () {
    [$user, $product] = watchedProduct();

    $product->update(['discount_percent' => 10]);

    Notification::assertSentTo($user, PriceDropNotification::class, fn ($n) => $n->newPrice === 900000.0);
});

test('price increases do not notify', function () {
    [$user, $product] = watchedProduct();

    $product->update(['price' => 1200000]);

    Notification::assertNothingSent();
});

test('with a target price the alert only fires once the price reaches it', function () {
    [$user, $product] = watchedProduct(800000);

    $product->update(['price' => 900000]);
    Notification::assertNothingSent();

    $product->update(['price' => 780000]);
    Notification::assertSentTo($user, PriceDropNotification::class);
});

test('the same price level is not announced twice', function () {
    [$user, $product] = watchedProduct();

    $product->update(['price' => 900000]);
    $product->update(['price' => 950000]);
    $product->update(['price' => 900000]);

    Notification::assertSentToTimes($user, PriceDropNotification::class, 1);

    $product->update(['price' => 850000]);
    Notification::assertSentToTimes($user, PriceDropNotification::class, 2);
});

test('unrelated changes do not trigger alerts', function () {
    [$user, $product] = watchedProduct();

    $product->update(['stock' => 1]);

    Notification::assertNothingSent();
});
