<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // 'icon' (mặc định, dùng cột icon+color có sẵn) hoặc 'image' (ảnh
            // thật upload Cloudinary, lưu ở MongoDB `category_images`).
            $table->string('display_type', 10)->default('icon')->after('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('display_type');
        });
    }
};
