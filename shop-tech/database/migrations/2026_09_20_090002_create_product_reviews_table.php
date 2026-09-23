<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 khách chỉ có 1 đánh giá / sản phẩm (unique) — gửi lại coi như sửa đánh giá
 * cũ (xem ProductReviewController::store, dùng updateOrCreate). order_item_id
 * nullable + nullOnDelete: gắn với đơn ĐÃ HOÀN TẤT để đánh dấu "đã mua hàng"
 * nếu tìm được, KHÔNG bắt buộc phải mua mới được đánh giá (giống nhiều sàn
 * TMĐT khác, chỉ gắn badge "Đã mua hàng" khi verify được).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            // products.id là int (signed) trong DB hiện tại -> khớp kiểu để tạo FK
            // (xem note tương tự ở order_items migration).
            $table->integer('product_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
