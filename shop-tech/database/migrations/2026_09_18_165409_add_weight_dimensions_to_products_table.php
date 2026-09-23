<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thông tin đóng gói cho GHN (weight = gram, length/width/height = cm). Nullable
 * — KHÔNG default — sản phẩm cũ chưa khai báo thì ShippingService từ chối tính
 * phí/tạo vận đơn (báo lỗi rõ ràng) thay vì tự bịa giá trị.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight')->nullable()->after('stock');
            $table->unsignedInteger('length')->nullable()->after('weight');
            $table->unsignedInteger('width')->nullable()->after('length');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight', 'length', 'width', 'height']);
        });
    }
};
