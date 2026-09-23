<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot ETA GHN lúc đặt hàng — trước đây ShippingService::quoteCart() đã
 * tính expected_delivery_time nhưng chỉ trả về cho FE lúc checkout rồi bỏ,
 * không lưu lại nên trang chi tiết đơn hàng không có gì để hiển thị.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('expected_delivery_time')->nullable()->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('expected_delivery_time');
        });
    }
};
