<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Việt Nam bỏ cấp quận/huyện từ 07/2025 (còn 2 cấp: tỉnh -> phường/xã, 34 tỉnh/thành).
 * Thay 3 cột text tự do (province/district/ward) bằng code (tra theo API
 * provinces.open-api.vn, xem App\Services\LocationService) + snapshot tên tại
 * thời điểm lưu, để hiển thị không phải gọi lại API và giữ đúng dữ liệu lịch sử.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('province_code')->nullable()->after('phone');
            $table->string('province_name')->nullable()->after('province_code');
            $table->unsignedInteger('ward_code')->nullable()->after('province_name');
            $table->string('ward_name')->nullable()->after('ward_code');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province', 'district', 'ward']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('province')->nullable()->after('phone');
            $table->string('district')->nullable()->after('province');
            $table->string('ward')->nullable()->after('district');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'province_name', 'ward_code', 'ward_name']);
        });
    }
};
