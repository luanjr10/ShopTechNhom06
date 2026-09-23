<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot địa chỉ giao hàng theo mã GHN tại thời điểm đặt hàng — trước đây Order
 * chỉ lưu `shipping_address` dạng string phẳng + `province_code` dùng tạm lúc
 * tính phí rồi bỏ, không đủ để tạo vận đơn GHN thật (cần district_id/ward_code
 * chính xác). Đổi profile địa chỉ của user sau khi đặt hàng KHÔNG ảnh hưởng đơn cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('ghn_province_id')->nullable()->after('shipping_address');
            $table->string('ghn_province_name')->nullable()->after('ghn_province_id');
            $table->unsignedInteger('ghn_district_id')->nullable()->after('ghn_province_name');
            $table->string('ghn_district_name')->nullable()->after('ghn_district_id');
            $table->string('ghn_ward_code')->nullable()->after('ghn_district_name');
            $table->string('ghn_ward_name')->nullable()->after('ghn_ward_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ghn_province_id', 'ghn_province_name', 'ghn_district_id',
                'ghn_district_name', 'ghn_ward_code', 'ghn_ward_name',
            ]);
        });
    }
};
