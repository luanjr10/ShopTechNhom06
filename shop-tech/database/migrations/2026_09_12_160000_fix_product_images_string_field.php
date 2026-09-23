<?php

use App\Models\ProductImage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Sửa lại các document `product_images` bị lưu sai kiểu dữ liệu
     * (mảng ảnh bị json_encode thành chuỗi thay vì mảng thật) do model
     * từng dùng cast 'array' không cần thiết với MongoDB.
     */
    public function up(): void
    {
        ProductImage::all()->each(function (ProductImage $productImage) {
            $images = $productImage->images;

            if (! is_string($images)) {
                return;
            }

            $decoded = json_decode($images, true);

            if (is_array($decoded)) {
                $productImage->images = $decoded;
                $productImage->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Không cần rollback dữ liệu.
    }
};
