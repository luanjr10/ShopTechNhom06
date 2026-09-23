<?php

use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\PayoutService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Schema;

/**
 * PayoutService: 'cod' duyệt trực tiếp (approveCod), 4 kênh online chỉ chốt
 * qua finalizeOnlinePayout() SAU KHI admin đã hoàn tất checkout trên sandbox
 * thật (xem WithdrawalPaymentController) — không còn tự sinh mã ngay lúc
 * bấm "Duyệt" như thiết kế cũ.
 */
beforeEach(function () {
    foreach (['withdrawal_requests', 'wallet_transactions', 'seller_wallets', 'seller_profiles', 'users'] as $table) {
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

    Schema::create('seller_profiles', function ($table) {
        $table->id();
        $table->foreignId('user_id')->unique();
        $table->string('display_name');
        $table->enum('status', ['active', 'suspended'])->default('active');
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

    Schema::create('withdrawal_requests', function ($table) {
        $table->id();
        $table->foreignId('seller_profile_id');
        $table->decimal('amount', 15, 2);
        $table->enum('method', ['cod', 'momo', 'vnpay', 'onepay', 'sepay'])->default('cod');
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->string('bank_account');
        $table->string('bank_name');
        $table->string('note')->nullable();
        $table->string('payout_reference')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->foreignId('reviewed_by')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    foreach (['withdrawal_requests', 'wallet_transactions', 'seller_wallets', 'seller_profiles', 'users'] as $table) {
        Schema::dropIfExists($table);
    }
});

function makeWithdrawal(string $method): array
{
    $user = User::factory()->create(['role' => 'seller']);
    $profile = SellerProfile::create(['user_id' => $user->id, 'display_name' => 'Seller', 'status' => 'active']);
    $wallet = SellerWallet::create(['seller_profile_id' => $profile->id, 'withdrawable_balance' => 500000, 'balance' => 500000]);
    $withdrawal = WithdrawalRequest::create([
        'seller_profile_id' => $profile->id,
        'amount' => 300000,
        'method' => $method,
        'status' => 'pending',
        'bank_account' => '0123456789',
        'bank_name' => 'Test',
    ]);
    // Mô phỏng đúng lúc tạo yêu cầu (Seller\WithdrawalController::store) — giữ
    // chỗ khỏi withdrawable để không rút trùng.
    app(WalletService::class)->reserveForWithdrawal($wallet, 300000, 'withdrawal_request', $withdrawal->id);

    return [$withdrawal, $wallet];
}

test('approveCod finalizes a cod withdrawal and pays out the wallet', function () {
    [$withdrawal, $wallet] = makeWithdrawal('cod');
    $admin = User::factory()->create(['role' => 'admin']);

    app(PayoutService::class)->approveCod($withdrawal, $admin->id);

    expect($withdrawal->refresh()->status)->toBe('approved');
    expect($withdrawal->reviewed_by)->toBe($admin->id);
    expect((float) $wallet->refresh()->withdrawable_balance)->toBe(200000.0);
    expect((float) $wallet->refresh()->balance)->toBe(200000.0);
});

test('approveCod refuses an online-method withdrawal', function () {
    [$withdrawal] = makeWithdrawal('momo');
    $admin = User::factory()->create(['role' => 'admin']);

    expect(fn () => app(PayoutService::class)->approveCod($withdrawal, $admin->id))
        ->toThrow(RuntimeException::class);

    expect($withdrawal->refresh()->status)->toBe('pending');
});

test('finalizeOnlinePayout finalizes an online withdrawal after sandbox checkout', function () {
    [$withdrawal, $wallet] = makeWithdrawal('momo');
    $withdrawal->update(['payout_reference' => 'MOMO-WD1-123']);

    app(PayoutService::class)->finalizeOnlinePayout($withdrawal);

    expect($withdrawal->refresh()->status)->toBe('approved');
    expect((float) $wallet->refresh()->withdrawable_balance)->toBe(200000.0);
});

test('finalizeOnlinePayout is idempotent (does not double-pay on repeated return calls)', function () {
    [$withdrawal, $wallet] = makeWithdrawal('vnpay');
    $withdrawal->update(['payout_reference' => 'VNPAY-WD1-123']);

    app(PayoutService::class)->finalizeOnlinePayout($withdrawal);
    app(PayoutService::class)->finalizeOnlinePayout($withdrawal);

    expect((float) $wallet->refresh()->withdrawable_balance)->toBe(200000.0);
});
