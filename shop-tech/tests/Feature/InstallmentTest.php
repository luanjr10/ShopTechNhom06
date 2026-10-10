<?php

use App\Models\InstallmentPayment;
use App\Models\Order;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\User;
use App\Notifications\InstallmentReminderNotification;
use App\Services\InstallmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

const INSTALLMENT_TABLES = ['wallet_transactions', 'seller_wallets', 'store_installment_settings', 'seller_orders', 'orders', 'stores', 'users'];

beforeEach(function () {
    foreach (INSTALLMENT_TABLES as $table) {
        Schema::dropIfExists($table);
    }
    createInstallmentTables();

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

    Schema::create('stores', function ($table) {
        $table->id();
        $table->unsignedBigInteger('seller_profile_id')->default(1);
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    Schema::create('store_installment_settings', function ($table) {
        $table->id();
        $table->unsignedBigInteger('store_id')->unique();
        $table->boolean('enabled')->default(false);
        $table->decimal('min_order', 15, 2)->default(3000000);
        $table->json('terms');
        $table->timestamps();
    });

    Schema::create('orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('status')->default('pending');
        $table->string('payment_method')->default('installment');
        $table->decimal('total_amount', 15, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('seller_orders', function ($table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('store_id')->default(1);
        $table->unsignedBigInteger('seller_profile_id')->default(1);
        $table->string('status')->default('pending');
        $table->decimal('subtotal', 15, 2)->default(0);
        $table->decimal('commission_rate', 5, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('seller_wallets', function ($table) {
        $table->id();
        $table->unsignedBigInteger('seller_profile_id')->unique();
        $table->decimal('balance', 15, 2)->default(0);
        $table->decimal('pending_balance', 15, 2)->default(0);
        $table->decimal('withdrawable_balance', 15, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('wallet_transactions', function ($table) {
        $table->id();
        $table->unsignedBigInteger('seller_wallet_id');
        $table->string('type');
        $table->decimal('amount', 15, 2);
        $table->decimal('balance_after', 15, 2)->default(0);
        $table->string('reference_type')->nullable();
        $table->unsignedBigInteger('reference_id')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });

    Carbon::setTestNow(Carbon::parse('2026-10-15 09:00:00', 'Asia/Ho_Chi_Minh'));
});

afterEach(function () {
    Carbon::setTestNow();
    foreach (INSTALLMENT_TABLES as $table) {
        Schema::dropIfExists($table);
    }
    foreach (['installment_payments', 'installment_plans'] as $table) {
        Schema::dropIfExists($table);
    }
});

function installmentStore(bool $enabled = true, float $minOrder = 3000000): Store
{
    static $n = 0;
    $n++;

    $store = Store::create(['name' => 'Shop '.$n, 'slug' => 'shop-'.$n.'-'.uniqid()]);
    app(InstallmentService::class)->updateSettings($store, [
        'enabled' => $enabled,
        'min_order' => $minOrder,
        'terms' => [['months' => 6, 'monthly_rate' => 0.8], ['months' => 3, 'monthly_rate' => 0]],
    ]);

    return $store;
}

function installmentOrder(User $user, Store $store, float $total, int $storeCount = 1): Order
{
    $orderId = DB::table('orders')->insertGetId(['user_id' => $user->id, 'total_amount' => $total]);

    foreach (range(1, $storeCount) as $i) {
        DB::table('seller_orders')->insert([
            'order_id' => $orderId,
            'store_id' => $i === 1 ? $store->id : $store->id + 100,
            'seller_profile_id' => 7,
            'subtotal' => $total,
            'commission_rate' => 10,
        ]);
    }

    return Order::find($orderId);
}

test('a zero-interest 3 month quote splits the principal and the last installment absorbs the remainder', function () {
    $quote = app(InstallmentService::class)->quote(10000000, 3, 0.0);

    expect($quote['total_interest'])->toBe(0.0)
        ->and($quote['total_payable'])->toBe(10000000.0)
        ->and(array_column($quote['schedule'], 'amount'))->toBe([3333333.0, 3333333.0, 3333334.0]);
});

test('a 12 month quote charges flat monthly interest and the schedule adds up exactly', function () {
    $quote = app(InstallmentService::class)->quote(12000000, 12, 1.2);

    expect($quote['total_interest'])->toBe(1728000.0)
        ->and($quote['total_payable'])->toBe(13728000.0)
        ->and(array_sum(array_column($quote['schedule'], 'amount')))->toBe(13728000.0)
        ->and(array_sum(array_column($quote['schedule'], 'principal_part')))->toBe(12000000.0);
});

test('a store that never configured installments is closed and shows the default terms', function () {
    $store = Store::create(['name' => 'Moi', 'slug' => 'moi']);
    $settings = app(InstallmentService::class)->settingsFor($store);

    expect($settings['enabled'])->toBeFalse()
        ->and(array_column($settings['options'], 'months'))->toBe([3, 6, 12]);
});

test('terms are saved sorted by number of months', function () {
    $store = installmentStore();

    expect(array_column(app(InstallmentService::class)->settingsFor($store)['options'], 'months'))->toBe([3, 6]);
});

test('a request becomes a pending plan with the store rate and monthly due dates', function () {
    $user = User::factory()->create();
    $store = installmentStore();

    $plan = app(InstallmentService::class)->createRequest(installmentOrder($user, $store, 6000000), $user, 6);

    expect($plan->status)->toBe('pending_approval')
        ->and($plan->store_id)->toBe($store->id)
        ->and((float) $plan->monthly_rate)->toBe(0.8)
        ->and($plan->payments)->toHaveCount(6)
        ->and($plan->payments->first()->due_date->toDateString())->toBe('2026-10-15')
        ->and($plan->payments->last()->due_date->toDateString())->toBe('2027-03-15')
        ->and((float) $plan->payments->sum('amount'))->toBe((float) $plan->total_payable);
});

test('requests are refused when the store has no installments, the order is too small, the term is unknown or the order mixes stores', function () {
    $service = app(InstallmentService::class);
    $user = User::factory()->create();

    $closed = installmentStore(enabled: false);
    expect(fn () => $service->createRequest(installmentOrder($user, $closed, 6000000), $user, 6))->toThrow(RuntimeException::class, 'không nhận bán trả góp');

    $open = installmentStore();
    expect(fn () => $service->createRequest(installmentOrder($user, $open, 1000000), $user, 6))->toThrow(RuntimeException::class, 'từ 3.000.000');
    expect(fn () => $service->createRequest(installmentOrder($user, $open, 6000000), $user, 12))->toThrow(RuntimeException::class, 'không có kỳ hạn');
    expect(fn () => $service->createRequest(installmentOrder($user, $open, 6000000, 2), $user, 6))->toThrow(RuntimeException::class, 'một gian hàng');
});

test('a customer with an overdue installment cannot open another one', function () {
    $service = app(InstallmentService::class);
    $user = User::factory()->create();
    $store = installmentStore();
    $plan = $service->createRequest(installmentOrder($user, $store, 6000000), $user, 3);
    $service->approve($plan);

    Carbon::setTestNow(Carbon::parse('2026-12-01', 'Asia/Ho_Chi_Minh'));

    expect(fn () => $service->createRequest(installmentOrder($user, $store, 6000000), $user, 3))->toThrow(RuntimeException::class, 'quá hạn');
});

test('the store approves a pending request once and a rejection closes the loan', function () {
    $service = app(InstallmentService::class);
    $user = User::factory()->create();
    $store = installmentStore();

    $approved = $service->approve($service->createRequest(installmentOrder($user, $store, 6000000), $user, 3), 'Khách quen');
    expect($approved->status)->toBe('active')
        ->and($approved->decision_note)->toBe('Khách quen')
        ->and($approved->decided_at)->not->toBeNull();
    expect(fn () => $service->approve($approved))->toThrow(RuntimeException::class, 'đã được xử lý');
    expect(fn () => $service->reject($approved))->toThrow(RuntimeException::class, 'đã được xử lý');

    $order = installmentOrder($user, $store, 6000000);
    $rejected = $service->reject($service->createRequest($order, $user, 3), 'Chưa đủ tin cậy');
    $service->cancelForOrder($order);

    expect($rejected->refresh()->status)->toBe('rejected')
        ->and($rejected->payments()->where('status', 'pending')->count())->toBe(0);
});

test('every paid installment credits the store wallet minus commission on the principal only', function () {
    $service = app(InstallmentService::class);
    $user = User::factory()->create();
    $store = installmentStore();
    $plan = $service->approve($service->createRequest(installmentOrder($user, $store, 6000000), $user, 6));
    $first = $plan->payments->first();

    $service->markPaid($first);
    $service->markPaid($first);

    $principalPart = (float) $first->principal_part;
    $expected = round((float) $first->amount - $principalPart * 0.10, 2);
    $wallet = SellerWallet::where('seller_profile_id', 7)->first();

    expect((float) $wallet->withdrawable_balance)->toBe($expected)
        ->and((float) $wallet->balance)->toBe($expected)
        ->and(DB::table('wallet_transactions')->count())->toBe(1);
});

test('paying every installment completes the plan and pays the store the interest too', function () {
    $service = app(InstallmentService::class);
    $user = User::factory()->create();
    $store = installmentStore();
    $plan = $service->approve($service->createRequest(installmentOrder($user, $store, 9000000), $user, 3));

    expect($service->outstandingPrincipal($user))->toBe(9000000.0);

    foreach ($plan->payments as $payment) {
        $service->markPaid($payment);
    }

    expect($plan->refresh()->status)->toBe('completed')
        ->and($service->outstandingPrincipal($user))->toBe(0.0);

    // 0% lãi: ví nhận tổng gốc trừ 10% hoa hồng sàn
    expect((float) SellerWallet::where('seller_profile_id', 7)->value('withdrawable_balance'))->toBe(8100000.0);
});

test('cancelling the order cancels a pending or active plan but keeps a rejection on record', function () {
    $service = app(InstallmentService::class);
    $user = User::factory()->create();
    $store = installmentStore();
    $order = installmentOrder($user, $store, 6000000);
    $plan = $service->approve($service->createRequest($order, $user, 3));

    $service->cancelForOrder($order);

    expect($plan->refresh()->status)->toBe('cancelled')
        ->and(InstallmentPayment::where('status', 'pending')->count())->toBe(0)
        ->and($service->outstandingPrincipal($user))->toBe(0.0);
});

test('reminder command only mails approved plans, once before the due date and once a day when overdue', function () {
    Notification::fake();
    $service = app(InstallmentService::class);
    $user = User::factory()->create();
    $store = installmentStore();

    $waiting = $service->createRequest(installmentOrder($user, $store, 6000000), $user, 3);
    $plan = $service->approve($service->createRequest(installmentOrder($user, $store, 6000000), $user, 6));
    $service->markPaid($plan->payments->first());

    Carbon::setTestNow(Carbon::parse('2026-11-13 08:00:00', 'Asia/Ho_Chi_Minh'));
    $this->artisan('installments:remind')->assertSuccessful();
    $this->artisan('installments:remind')->assertSuccessful();
    Notification::assertSentToTimes($user, InstallmentReminderNotification::class, 1);

    Carbon::setTestNow(Carbon::parse('2026-11-17 08:00:00', 'Asia/Ho_Chi_Minh'));
    $this->artisan('installments:remind')->assertSuccessful();
    Notification::assertSentToTimes($user, InstallmentReminderNotification::class, 2);

    expect($waiting->refresh()->status)->toBe('pending_approval');
});

test('the decision summary tells the store whether the customer is already late somewhere', function () {
    $service = app(InstallmentService::class);
    $user = User::factory()->create();
    $store = installmentStore();
    $plan = $service->approve($service->createRequest(installmentOrder($user, $store, 6000000), $user, 3));

    expect($service->summary($user))->toMatchArray(['has_overdue' => false, 'outstanding' => 6000000.0]);

    Carbon::setTestNow(Carbon::parse('2026-12-01', 'Asia/Ho_Chi_Minh'));
    expect($service->summary($user)['has_overdue'])->toBeTrue()
        ->and($plan->refresh()->status)->toBe('active');
});
