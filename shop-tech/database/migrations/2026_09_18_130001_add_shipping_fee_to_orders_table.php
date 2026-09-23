<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Phí ship do ShippingService tính lại ở BE lúc đặt hàng (không tin FE),
            // cộng vào total_amount. Snapshot — không đổi lại khi đơn đã tạo.
            $table->decimal('shipping_fee', 15, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
    }
};
