<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Cùng cách ly như các test khác (xem SellerAdminIsolationTest): tự tạo schema
 * tối giản, không dùng RefreshDatabase. LocationService gọi provinces.open-api.vn
 * thật -> fake bằng Http::fake() để test không phụ thuộc mạng/uptime bên thứ 3.
 */
beforeEach(function () {
    Cache::flush();

    Schema::dropIfExists('addresses');
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

    Schema::create('addresses', function ($table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('recipient_name');
        $table->string('phone', 20);
        $table->unsignedInteger('province_code')->nullable();
        $table->string('province_name')->nullable();
        $table->unsignedInteger('ward_code')->nullable();
        $table->string('ward_name')->nullable();
        $table->string('address_line');
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });

    Http::fake([
        'provinces.open-api.vn/api/v2/p/' => Http::response([
            ['name' => 'Tỉnh Ninh Bình', 'code' => 37, 'division_type' => 'tỉnh', 'codename' => 'ninh_binh', 'phone_code' => 229],
            ['name' => 'Thành phố Hà Nội', 'code' => 1, 'division_type' => 'thành phố trung ương', 'codename' => 'ha_noi', 'phone_code' => 24],
        ], 200),
        'provinces.open-api.vn/api/v2/p/37*' => Http::response([
            'name' => 'Tỉnh Ninh Bình',
            'code' => 37,
            'wards' => [
                ['name' => 'Phường Hoa Lư', 'code' => 13333, 'division_type' => 'phường', 'codename' => 'phuong_hoa_lu', 'province_code' => 37],
            ],
        ], 200),
        'provinces.open-api.vn/api/v2/p/1*' => Http::response([
            'name' => 'Thành phố Hà Nội',
            'code' => 1,
            'wards' => [
                ['name' => 'Phường Ba Đình', 'code' => 4, 'division_type' => 'phường', 'codename' => 'phuong_ba_dinh', 'province_code' => 1],
            ],
        ], 200),
    ]);
});

afterEach(function () {
    Schema::dropIfExists('addresses');
    Schema::dropIfExists('users');
});

function loginAsAddressOwner(): User
{
    $user = User::factory()->create(['role' => 'customer']);
    $token = Auth::guard('api')->login($user);
    test()->withHeader('Authorization', "Bearer {$token}");

    return $user;
}

test('provinces endpoint returns the proxied 34-province data', function () {
    test()->getJson('/api/locations/provinces')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('wards endpoint returns wards for a valid province', function () {
    test()->getJson('/api/locations/provinces/37/wards')
        ->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Phường Hoa Lư');
});

test('wards endpoint 404s for an unknown province code', function () {
    test()->getJson('/api/locations/provinces/999999/wards')->assertStatus(404);
});

test('creating an address snapshots the real province/ward names, not client supplied ones', function () {
    loginAsAddressOwner();

    test()->postJson('/api/addresses', [
        'recipient_name' => 'Nguyen Van A',
        'phone' => '0900000000',
        'province_code' => 37,
        'ward_code' => 13333,
        'address_line' => 'Số 1, ngõ 2',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.province_name', 'Tỉnh Ninh Bình')
        ->assertJsonPath('data.ward_name', 'Phường Hoa Lư');
});

test('creating an address with a ward that does not belong to the province is rejected', function () {
    loginAsAddressOwner();

    // ward 4 (Ba Đình) thuộc province 1, không thuộc province 37.
    test()->postJson('/api/addresses', [
        'recipient_name' => 'Nguyen Van A',
        'phone' => '0900000000',
        'province_code' => 37,
        'ward_code' => 4,
        'address_line' => 'Số 1, ngõ 2',
    ])->assertStatus(422);
});

test('a user cannot update another users address', function () {
    $userA = loginAsAddressOwner();
    $addressA = $userA->addresses()->create([
        'recipient_name' => 'A',
        'phone' => '0900000000',
        'province_code' => 37,
        'province_name' => 'Tỉnh Ninh Bình',
        'ward_code' => 13333,
        'ward_name' => 'Phường Hoa Lư',
        'address_line' => 'Số 1',
        'is_default' => true,
    ]);

    loginAsAddressOwner(); // user B

    test()->patchJson("/api/addresses/{$addressA->id}", ['address_line' => 'hacked'])
        ->assertStatus(403);
});
