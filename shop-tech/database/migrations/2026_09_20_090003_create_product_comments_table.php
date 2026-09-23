<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Hỏi & đáp" của sản phẩm — bất kỳ khách nào cũng bình luận/trả lời được,
 * không giới hạn 1 seller. parent_id trỏ về comment gốc (BE luôn "làm phẳng"
 * về tối đa 2 cấp — xem ProductCommentController::store — nên UI chỉ cần
 * render câu hỏi + danh sách phản hồi phẳng bên dưới, không cần đệ quy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_comments', function (Blueprint $table) {
            $table->id();
            // products.id là int (signed) trong DB hiện tại -> khớp kiểu để tạo FK.
            $table->integer('product_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comments');
    }
};
