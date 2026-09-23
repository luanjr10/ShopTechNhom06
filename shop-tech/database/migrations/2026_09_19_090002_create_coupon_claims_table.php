<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ví voucher" của khách — khách bấm Nhận 1 voucher hạng thành viên trước khi
 * dùng được (xem CouponService::apply). Không lưu số lần dùng ở đây, xem
 * coupon_redemptions cho việc đó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_claims');
    }
};
