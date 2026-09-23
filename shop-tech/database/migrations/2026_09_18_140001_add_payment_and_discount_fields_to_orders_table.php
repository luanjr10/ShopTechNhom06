<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Mã giảm giá áp dụng lúc đặt hàng (snapshot — không đổi lại sau đó).
            $table->string('discount_code')->nullable()->after('payment_method');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_code');
            // orderId/requestId gửi cho cổng thanh toán (MoMo/VNPay) — khác id nội bộ
            // vì cổng yêu cầu 1 mã duy nhất mỗi lần tạo request, dùng để đối soát ở return/IPN.
            $table->string('payment_ref')->nullable()->after('discount_amount');
            $table->timestamp('paid_at')->nullable()->after('payment_ref');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_code', 'discount_amount', 'payment_ref', 'paid_at']);
        });
    }
};
