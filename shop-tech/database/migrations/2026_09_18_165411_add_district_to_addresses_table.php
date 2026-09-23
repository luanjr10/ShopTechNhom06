<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm quận/huyện (mã GHN) vào Address — GHN vẫn dùng hệ 3 cấp (tỉnh/quận-huyện/
 * phường) cho API Tính phí, khác hệ 2 cấp provinces.open-api.vn cũ.
 *
 * `province_code`/`ward_code` cũ (provinces.open-api.vn) GIỮ NGUYÊN, không xoá,
 * nhưng KHÔNG còn được ghi mới — không có bảng chuyển đổi 1-1 đáng tin cậy giữa
 * 2 hệ mã khác nhau (tự suy luận sẽ là đoán mò, rủi ro sai). Từ nay
 * `district_id`/`ward_code_ghn` mới là nguồn DUY NHẤT dùng để tính phí/tạo vận đơn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('province_id_ghn')->nullable()->after('ward_name');
            $table->string('province_name_ghn')->nullable()->after('province_id_ghn');
            $table->unsignedInteger('district_id')->nullable()->after('province_name_ghn');
            $table->string('district_name')->nullable()->after('district_id');
            $table->string('ward_code_ghn')->nullable()->after('district_name');
            $table->string('ward_name_ghn')->nullable()->after('ward_code_ghn');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn([
                'province_id_ghn', 'province_name_ghn', 'district_id', 'district_name',
                'ward_code_ghn', 'ward_name_ghn',
            ]);
        });
    }
};
