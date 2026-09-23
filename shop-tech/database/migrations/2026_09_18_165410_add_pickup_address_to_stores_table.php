<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Địa chỉ lấy hàng (pickup/from) của từng Store — GHN cần district_id/ward_code
 * theo hệ CŨ (có quận/huyện), không dùng chung hệ province/ward mới nữa (xem
 * LocationService — đã đổi nguồn sang master-data cũ của GHN).
 *
 * Store cũ (seed trước đây) chưa có địa chỉ — seed 1 địa chỉ mặc định hợp lệ
 * (đã verify thật qua GHN API, xem down() của migration seed riêng) để không
 * chặn luồng test; seller có thể tự cập nhật lại qua Seller Center sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('pickup_contact_name')->nullable()->after('status');
            $table->string('pickup_phone')->nullable()->after('pickup_contact_name');
            $table->unsignedInteger('province_id')->nullable()->after('pickup_phone');
            $table->string('province_name')->nullable()->after('province_id');
            $table->unsignedInteger('district_id')->nullable()->after('province_name');
            $table->string('district_name')->nullable()->after('district_id');
            $table->string('ward_code')->nullable()->after('district_name');
            $table->string('ward_name')->nullable()->after('ward_code');
            $table->string('address_line')->nullable()->after('ward_name');
        });

        // Seed địa chỉ mặc định cho store cũ chưa có — Quận Cầu Giấy, Phường Nghĩa
        // Tân, Hà Nội (district_id=1485, ward_code=1A0605) — verify thật qua GHN
        // API ngày tích hợp (SupportType=3: hỗ trợ cả lấy lẫn giao). Seller cần tự
        // cập nhật đúng địa chỉ kho thật của mình qua Seller Center.
        DB::table('stores')->whereNull('district_id')->update([
            'pickup_contact_name' => DB::raw('COALESCE(pickup_contact_name, name)'),
            'pickup_phone' => '0900000000',
            'province_id' => 201,
            'province_name' => 'Hà Nội',
            'district_id' => 1485,
            'district_name' => 'Quận Cầu Giấy',
            'ward_code' => '1A0605',
            'ward_name' => 'Phường Nghĩa Tân',
            'address_line' => '(Địa chỉ mặc định — seller vui lòng cập nhật đúng địa chỉ kho thật)',
        ]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_contact_name', 'pickup_phone', 'province_id', 'province_name',
                'district_id', 'district_name', 'ward_code', 'ward_name', 'address_line',
            ]);
        });
    }
};
