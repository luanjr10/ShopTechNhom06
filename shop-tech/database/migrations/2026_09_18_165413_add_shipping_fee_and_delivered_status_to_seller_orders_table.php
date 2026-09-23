<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phí ship tính RIÊNG theo từng seller_order (mỗi seller có địa chỉ kho khác
 * nhau, GHN trả giá khác nhau theo tuyến) — trước đây shipping_fee chỉ có ở
 * `orders` (1 giá chung cho cả đơn multi-seller, sai bản chất GHN).
 *
 * Thêm status `delivered` vào enum: pending→confirmed→shipping→delivered→completed.
 * `delivered` CHỈ được set bởi webhook GHN (SellerOrderService), seller không tự
 * set qua route PATCH status thường (xem SellerOrderController::updateStatus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('shipping_fee', 15, 2)->default(0)->after('subtotal');
        });

        DB::statement("ALTER TABLE seller_orders MODIFY status ENUM('pending','confirmed','shipping','delivered','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE seller_orders SET status = 'completed' WHERE status = 'delivered'");
        DB::statement("ALTER TABLE seller_orders MODIFY status ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
    }
};
