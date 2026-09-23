<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            // products.id là int (signed) trong DB hiện tại -> khớp kiểu để tạo FK.
            $table->integer('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            // Variant không có id thật (chỉ là JSON trong Mongo) -> khớp theo sku.
            // NOT NULL + default '' (không phải nullable) để unique constraint dưới
            // đảm bảo được ở tầng DB: sản phẩm không variant chỉ có đúng 1 dòng/cart.
            $table->string('sku')->default('');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
