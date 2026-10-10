<?php

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function something() {}

/**
 * Các service huỷ đơn giờ đụng tới bảng trả góp; test tự dựng schema cần có sẵn bảng rỗng này.
 */
function createInstallmentTables(): void
{
    foreach (['installment_payments', 'installment_plans'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('installment_plans', function ($table) {
        $table->id();
        $table->unsignedBigInteger('order_id')->unique();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('store_id')->nullable();
        $table->unsignedBigInteger('seller_order_id')->nullable();
        $table->decimal('principal', 15, 2);
        $table->unsignedTinyInteger('months');
        $table->decimal('monthly_rate', 5, 2);
        $table->decimal('total_interest', 15, 2);
        $table->decimal('total_payable', 15, 2);
        $table->string('status')->default('pending_approval');
        $table->timestamp('decided_at')->nullable();
        $table->string('decision_note')->nullable();
        $table->timestamps();
    });

    Schema::create('installment_payments', function ($table) {
        $table->id();
        $table->unsignedBigInteger('plan_id');
        $table->unsignedTinyInteger('number');
        $table->decimal('amount', 15, 2);
        $table->decimal('principal_part', 15, 2);
        $table->decimal('interest_part', 15, 2);
        $table->date('due_date');
        $table->string('status')->default('pending');
        $table->timestamp('paid_at')->nullable();
        $table->string('payment_ref')->nullable();
        $table->timestamp('reminded_at')->nullable();
        $table->timestamps();
        $table->unique(['plan_id', 'number']);
    });
}
