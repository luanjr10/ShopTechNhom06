<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Hỏi & đáp" theo DANH MỤC (khác `product_comments` — gắn với 1 sản phẩm cụ
 * thể). Trang danh mục trước đây hiển thị Hỏi & đáp bằng dữ liệu giả tĩnh —
 * bảng này cho phép nó hoạt động thật, cùng khuôn mẫu 2 cấp phẳng với
 * ProductComment (xem CategoryCommentController::store).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_comments', function (Blueprint $table) {
            $table->id();
            // categories.id là int (signed) trong DB hiện tại -> khớp kiểu để tạo FK.
            $table->integer('category_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('category_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_comments');
    }
};
