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
            $table->boolean('new_customer_only')->default(false);
            $table->unsignedTinyInteger('weekday')->nullable();
        });

        $base = [
            'target_tier' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'expires_at' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $newCustomer = [
            ['code' => 'CHAOMUNG', 'title' => 'Chào bạn mới - Giảm 10%', 'description' => 'Giảm 10%, tối đa 100.000đ cho đơn hàng đầu tiên từ 200.000đ.',
                'type' => 'percent', 'is_free_ship' => false, 'value' => 10, 'max_discount' => 100000, 'min_order_amount' => 200000],
            ['code' => 'FREESHIP-MOI', 'title' => 'Chào bạn mới - Miễn phí ship', 'description' => 'Miễn phí vận chuyển (tối đa 30.000đ) cho đơn hàng đầu tiên.',
                'type' => 'free_ship', 'is_free_ship' => true, 'value' => 0, 'max_discount' => 30000, 'min_order_amount' => 0],
        ];

        foreach ($newCustomer as $row) {
            DB::table('coupons')->insert($row + $base + ['per_user_limit' => 1, 'new_customer_only' => true, 'weekday' => null]);
        }

        $daily = [
            1 => ['code' => 'THU2-FREESHIP', 'title' => 'Thứ Hai miễn phí ship', 'description' => 'Miễn phí vận chuyển tối đa 30.000đ cho đơn từ 150.000đ.',
                'type' => 'free_ship', 'is_free_ship' => true, 'value' => 0, 'max_discount' => 30000, 'min_order_amount' => 150000],
            2 => ['code' => 'THU3-GIAM5', 'title' => 'Thứ Ba giảm 5%', 'description' => 'Giảm 5%, tối đa 50.000đ cho đơn từ 200.000đ.',
                'type' => 'percent', 'is_free_ship' => false, 'value' => 5, 'max_discount' => 50000, 'min_order_amount' => 200000],
            3 => ['code' => 'THU4-GIAM30K', 'title' => 'Thứ Tư giảm 30.000đ', 'description' => 'Giảm 30.000đ cho đơn từ 300.000đ.',
                'type' => 'fixed', 'is_free_ship' => false, 'value' => 30000, 'max_discount' => null, 'min_order_amount' => 300000],
            4 => ['code' => 'THU5-FREESHIP', 'title' => 'Thứ Năm miễn phí ship', 'description' => 'Miễn phí vận chuyển tối đa 20.000đ, mọi đơn hàng.',
                'type' => 'free_ship', 'is_free_ship' => true, 'value' => 0, 'max_discount' => 20000, 'min_order_amount' => 0],
            5 => ['code' => 'THU6-GIAM7', 'title' => 'Thứ Sáu giảm 7%', 'description' => 'Giảm 7%, tối đa 70.000đ cho đơn từ 300.000đ.',
                'type' => 'percent', 'is_free_ship' => false, 'value' => 7, 'max_discount' => 70000, 'min_order_amount' => 300000],
            6 => ['code' => 'THU7-GIAM100K', 'title' => 'Thứ Bảy giảm 100.000đ', 'description' => 'Giảm 100.000đ cho đơn từ 1.000.000đ.',
                'type' => 'fixed', 'is_free_ship' => false, 'value' => 100000, 'max_discount' => null, 'min_order_amount' => 1000000],
            0 => ['code' => 'CN-GIAM8', 'title' => 'Chủ Nhật giảm 8%', 'description' => 'Giảm 8%, tối đa 100.000đ cho đơn từ 500.000đ.',
                'type' => 'percent', 'is_free_ship' => false, 'value' => 8, 'max_discount' => 100000, 'min_order_amount' => 500000],
        ];

        foreach ($daily as $weekday => $row) {
            DB::table('coupons')->insert($row + $base + ['per_user_limit' => null, 'new_customer_only' => false, 'weekday' => $weekday]);
        }
    }

    public function down(): void
    {
        DB::table('coupons')->where('new_customer_only', true)->orWhereNotNull('weekday')->delete();

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['new_customer_only', 'weekday']);
        });
    }
};
