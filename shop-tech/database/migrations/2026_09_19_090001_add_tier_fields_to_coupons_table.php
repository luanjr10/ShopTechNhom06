<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('title')->nullable()->after('code');
            $table->string('description')->nullable()->after('title');
            // null = áp dụng cho MỌI hạng khách hàng; nếu set, khách phải đạt
            // TỐI THIỂU hạng này mới được nhận/dùng (xem CustomerTierService).
            $table->string('target_tier')->nullable()->after('type');
            $table->boolean('is_free_ship')->default(false)->after('target_tier');
            // Số lần TỐI ĐA 1 khách được dùng mã này (null = không giới hạn riêng
            // từng khách, chỉ còn bị chặn bởi usage_limit tổng toàn sàn).
            $table->unsignedInteger('per_user_limit')->nullable()->after('usage_limit');
        });

        // `type` vốn là enum cứng ['percent','fixed'] — cần thêm 'free_ship'.
        // Đổi bằng add-copy-drop-rename (portable mysql/sqlite, không cần
        // doctrine/dbal mà Schema::table()->change() yêu cầu).
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('type_v2')->default('percent')->after('type');
        });
        DB::table('coupons')->update(['type_v2' => DB::raw('type')]);
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->renameColumn('type_v2', 'type');
        });

        // Voucher hạng thành viên demo — miễn phí ship 3 lần cho khách Bạc trở lên.
        DB::table('coupons')->insert([
            [
                'code' => 'FREESHIP-BAC',
                'title' => 'Miễn phí vận chuyển - Hạng Bạc',
                'description' => 'Miễn phí ship cho khách hàng đạt hạng Bạc trở lên, tối đa 3 đơn.',
                'type' => 'free_ship',
                'target_tier' => 'bac',
                'is_free_ship' => true,
                'value' => 0,
                'max_discount' => null,
                'min_order_amount' => 0,
                'usage_limit' => null,
                'per_user_limit' => 3,
                'used_count' => 0,
                'expires_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'VANG-GIAM5',
                'title' => 'Ưu đãi hạng Vàng',
                'description' => 'Giảm 5%, tối đa 200.000đ cho khách hàng hạng Vàng trở lên.',
                'type' => 'percent',
                'target_tier' => 'vang',
                'is_free_ship' => false,
                'value' => 5,
                'max_discount' => 200000,
                'min_order_amount' => 0,
                'usage_limit' => null,
                'per_user_limit' => null,
                'used_count' => 0,
                'expires_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'KIMCUONG-GIAM10',
                'title' => 'Ưu đãi hạng Kim Cương',
                'description' => 'Giảm 10%, tối đa 500.000đ dành riêng cho khách hàng hạng Kim Cương.',
                'type' => 'percent',
                'target_tier' => 'kim_cuong',
                'is_free_ship' => false,
                'value' => 10,
                'max_discount' => 500000,
                'min_order_amount' => 0,
                'usage_limit' => null,
                'per_user_limit' => null,
                'used_count' => 0,
                'expires_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'target_tier', 'is_free_ship', 'per_user_limit']);
        });
    }
};
