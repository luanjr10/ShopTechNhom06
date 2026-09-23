<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yêu cầu hoàn trả / bảo hành của khách cho 1 dòng sản phẩm (order_item) đã
 * nhận hàng thành công (seller_order.status = completed). seller_order_id
 * được lưu thêm (denormalized) để seller lọc theo store của mình nhanh, thay
 * vì phải join qua order_items->seller_orders mỗi lần.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['return', 'warranty']);
            $table->text('reason');
            $table->json('images'); // bắt buộc >= 1 ảnh, xem CustomerReturnController::store
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('seller_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
