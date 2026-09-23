<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 vận đơn GHN cho 1 SellerOrder (mỗi seller bàn giao/gửi hàng riêng — KHÔNG
 * gắn vào Order cha, vì 1 Order có thể tách nhiều seller_order theo store).
 * unique(seller_order_id) chặn tạo 2 vận đơn cho cùng 1 seller_order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->string('provider')->default('ghn');
            // Mã vận đơn GHN (order_code) — dùng cho mọi API sau: tra cứu, huỷ, webhook khớp đơn.
            $table->string('tracking_number')->nullable()->index();
            // Mã đơn nội bộ gửi cho GHN (client_order_code) — cho phép gọi lại an
            // toàn (idempotent): gửi lại cùng mã sẽ nhận lại đúng order_code cũ.
            $table->string('client_order_code')->nullable();
            $table->unsignedTinyInteger('service_type_id')->nullable();
            // Phí GHN báo — theo quyết định business: KHÔNG markup, đây cũng chính
            // là số tiền khách đã trả trong shipping_fee của seller_order.
            $table->decimal('fee', 15, 2)->nullable();
            $table->unsignedInteger('weight_gram')->nullable();
            $table->unsignedInteger('length_cm')->nullable();
            $table->unsignedInteger('width_cm')->nullable();
            $table->unsignedInteger('height_cm')->nullable();
            // Trạng thái carrier riêng (theo đúng enum GHN trả về ở data.status/webhook
            // Status — xem GhnProvider::STATUS_MAP) — KHÔNG trùng seller_orders.status.
            $table->string('status')->nullable();
            $table->timestamp('expected_delivery_time')->nullable();
            // Lưu response đầy đủ lúc tạo đơn — audit/debug, không tự bịa field
            // ngoài những gì GHN trả về.
            $table->json('provider_response')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
