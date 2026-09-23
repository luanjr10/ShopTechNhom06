<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ảnh sản phẩm được chuyển sang lưu ở MongoDB (collection `product_images`)
     * để hỗ trợ nhiều ảnh trên mỗi sản phẩm, nên không cần cột này trong MySQL nữa.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'thumbnail')) {
                $table->dropColumn('thumbnail');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'thumbnail')) {
                $table->string('thumbnail')->nullable();
            }
        });
    }
};
