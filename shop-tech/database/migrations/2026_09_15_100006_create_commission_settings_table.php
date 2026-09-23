<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['default', 'category', 'store'])->default('default')->index();
            // categories.id là int (signed) trong DB hiện tại -> khớp kiểu để tạo FK.
            $table->integer('category_id')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->decimal('rate', 5, 2); // phần trăm hoa hồng, vd 10.00
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settings');
    }
};
