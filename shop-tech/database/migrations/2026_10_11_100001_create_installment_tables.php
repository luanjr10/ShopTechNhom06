<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cod')->change();
        });

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('principal', 15, 2);
            $table->unsignedTinyInteger('months');
            $table->decimal('monthly_rate', 5, 2);
            $table->decimal('total_interest', 15, 2);
            $table->decimal('total_payable', 15, 2);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_part', 15, 2);
            $table->decimal('interest_part', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_ref')->nullable()->index();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'number']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_plans');
    }
};
