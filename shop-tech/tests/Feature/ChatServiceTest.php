<?php

use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Schema;

const CHAT_TABLES = ['chat_messages', 'chat_conversations', 'stores', 'seller_profiles', 'users'];

beforeEach(function () {
    foreach (CHAT_TABLES as $table) {
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

    Schema::create('seller_profiles', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->unique();
        $table->string('display_name');
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::create('stores', function ($table) {
        $table->id();
        $table->unsignedBigInteger('seller_profile_id');
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::create('chat_conversations', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('store_id')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->string('last_message_preview')->nullable();
        $table->unsignedInteger('customer_unread')->default(0);
        $table->unsignedInteger('store_unread')->default(0);
        $table->timestamps();
        $table->unique(['user_id', 'store_id']);
    });

    Schema::create('chat_messages', function ($table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->string('sender_type');
        $table->unsignedBigInteger('sender_id');
        $table->text('body')->nullable();
        $table->integer('product_id')->nullable();
        $table->json('attachments')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    foreach (CHAT_TABLES as $table) {
        Schema::dropIfExists($table);
    }
});

function chatStore(string $status = 'active'): array
{
    $seller = User::factory()->create(['role' => 'seller']);
    $profile = SellerProfile::create(['user_id' => $seller->id, 'display_name' => 'Seller']);
    $store = Store::create(['seller_profile_id' => $profile->id, 'name' => 'Store', 'slug' => 'store-'.$profile->id, 'status' => $status]);

    return [$seller, $store];
}

test('a customer gets the same conversation each time for a store', function () {
    [, $store] = chatStore();
    $customer = User::factory()->create();
    $chat = app(ChatService::class);

    $first = $chat->startConversation($customer, $store->load('sellerProfile'));
    $second = $chat->startConversation($customer, $store);

    expect($second->id)->toBe($first->id);
});

test('a seller cannot chat with their own store and inactive stores refuse chats', function () {
    [$seller, $store] = chatStore();
    [, $inactive] = chatStore('inactive');
    $chat = app(ChatService::class);

    expect(fn () => $chat->startConversation($seller, $store->load('sellerProfile')))
        ->toThrow(RuntimeException::class, 'chính mình');
    expect(fn () => $chat->startConversation(User::factory()->create(), $inactive))
        ->toThrow(RuntimeException::class, 'không hoạt động');
});

test('sending raises the unread counter of the other side and a read clears it', function () {
    [$seller, $store] = chatStore();
    $customer = User::factory()->create();
    $chat = app(ChatService::class);
    $conversation = $chat->startConversation($customer, $store->load('sellerProfile'));

    $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, 'Máy còn hàng không shop?');
    $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, 'Giao Hà Nội mất mấy ngày?');
    $conversation->refresh();

    expect($conversation->store_unread)->toBe(2)
        ->and($conversation->customer_unread)->toBe(0)
        ->and($conversation->last_message_preview)->toBe('Giao Hà Nội mất mấy ngày?')
        ->and($chat->unreadForStore($store))->toBe(2);

    $chat->markRead($conversation, ChatService::SIDE_STORE);
    $chat->send($conversation, ChatService::SIDE_STORE, $seller, 'Còn hàng bạn nhé!');

    expect($conversation->refresh()->store_unread)->toBe(0)
        ->and($conversation->customer_unread)->toBe(1)
        ->and($chat->unreadForCustomer($customer))->toBe(1);
});

test('polling with after_id only returns newer messages in order', function () {
    [, $store] = chatStore();
    $customer = User::factory()->create();
    $chat = app(ChatService::class);
    $conversation = $chat->startConversation($customer, $store->load('sellerProfile'));

    $first = $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, 'một');
    $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, 'hai');
    $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, 'ba');

    expect($chat->messages($conversation)->pluck('body')->all())->toBe(['một', 'hai', 'ba'])
        ->and($chat->messages($conversation, $first->id)->pluck('body')->all())->toBe(['hai', 'ba']);
});

test('blank messages are rejected', function () {
    [, $store] = chatStore();
    $customer = User::factory()->create();
    $chat = app(ChatService::class);
    $conversation = $chat->startConversation($customer, $store->load('sellerProfile'));

    expect(fn () => $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, "   \n "))
        ->toThrow(RuntimeException::class, 'không được để trống');
});

test('support conversations belong to no store and are reused per customer', function () {
    $customer = User::factory()->create();
    $chat = app(ChatService::class);

    $first = $chat->startSupportConversation($customer);
    $second = $chat->startSupportConversation($customer);

    expect($second->id)->toBe($first->id)
        ->and($first->store_id)->toBeNull()
        ->and($first->isSupport())->toBeTrue();
});

test('support threads count unread for staff separately from store inboxes', function () {
    [, $store] = chatStore();
    $customer = User::factory()->create();
    $staff = User::factory()->create(['role' => 'admin']);
    $chat = app(ChatService::class);

    $support = $chat->startSupportConversation($customer);
    $storeChat = $chat->startConversation($customer, $store->load('sellerProfile'));

    $chat->send($support, ChatService::SIDE_CUSTOMER, $customer, 'Đơn của tôi bị chậm');
    $chat->send($storeChat, ChatService::SIDE_CUSTOMER, $customer, 'Còn hàng không?');

    expect($chat->unreadForStaff())->toBe(1)
        ->and($chat->unreadForStore($store))->toBe(1);

    $chat->markRead($support, ChatService::SIDE_STORE);
    $chat->send($support, ChatService::SIDE_STORE, $staff, 'Shop kiểm tra giúp bạn nhé');

    expect($chat->unreadForStaff())->toBe(0)
        ->and($chat->unreadForCustomer($customer))->toBe(1);
});

test('a message can carry only attachments and the preview describes them', function () {
    [, $store] = chatStore();
    $customer = User::factory()->create();
    $chat = app(ChatService::class);
    $conversation = $chat->startConversation($customer, $store->load('sellerProfile'));

    $image = ['type' => 'image', 'url' => 'https://img/a.jpg', 'name' => 'a.jpg', 'size' => 1200];
    $file = ['type' => 'file', 'url' => 'https://img/b.pdf', 'name' => 'bao-gia.pdf', 'size' => 5000];

    $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, null, null, [$image, $image]);
    expect($conversation->refresh()->last_message_preview)->toBe('[Đã gửi 2 ảnh]');

    $message = $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, '', null, [$file]);
    expect($conversation->refresh()->last_message_preview)->toBe('[Đã gửi tệp đính kèm]')
        ->and($message->refresh()->attachments)->toBe([$file])
        ->and($conversation->store_unread)->toBe(2);
});

test('text with an attachment keeps the text as the preview', function () {
    [, $store] = chatStore();
    $customer = User::factory()->create();
    $chat = app(ChatService::class);
    $conversation = $chat->startConversation($customer, $store->load('sellerProfile'));

    $chat->send($conversation, ChatService::SIDE_CUSTOMER, $customer, 'Đây là ảnh lỗi máy', null, [
        ['type' => 'image', 'url' => 'https://img/a.jpg', 'name' => 'a.jpg', 'size' => 10],
    ]);

    expect($conversation->refresh()->last_message_preview)->toBe('Đây là ảnh lỗi máy');
});
