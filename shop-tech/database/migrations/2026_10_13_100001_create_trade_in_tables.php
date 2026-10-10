<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_in_models', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->string('brand', 60);
            $table->string('name', 150);
            $table->decimal('base_price', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('trade_in_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_in_model_id')->nullable()->constrained('trade_in_models')->nullOnDelete();
            $table->string('model_name', 150);
            $table->string('condition', 20);
            $table->boolean('has_box')->default(false);
            $table->boolean('has_charger')->default(false);
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->decimal('estimated_price', 15, 2);
            $table->decimal('final_price', 15, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'used'])->default('pending');
            $table->string('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->unsignedBigInteger('used_order_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('trade_in_request_id')->nullable()->index();
        });

        $now = now();
        $samples = [
            ['phone', 'Apple', 'iPhone 12 128GB', 6800000],
            ['phone', 'Apple', 'iPhone 13 128GB', 9500000],
            ['phone', 'Apple', 'iPhone 14 Pro 128GB', 16000000],
            ['phone', 'Samsung', 'Galaxy S22 128GB', 6000000],
            ['phone', 'Samsung', 'Galaxy S23 Ultra 256GB', 14000000],
            ['laptop', 'Apple', 'MacBook Air M1 8GB/256GB', 12500000],
            ['laptop', 'Dell', 'XPS 13 9310 i7', 9000000],
            ['tablet', 'Apple', 'iPad Air 4 64GB', 7000000],
            ['watch', 'Apple', 'Apple Watch Series 7 45mm', 4000000],
        ];

        foreach ($samples as [$category, $brand, $name, $price]) {
            DB::table('trade_in_models')->insert([
                'category' => $category,
                'brand' => $brand,
                'name' => $name,
                'base_price' => $price,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'trade_in_request_id']);
        });

        Schema::dropIfExists('trade_in_requests');
        Schema::dropIfExists('trade_in_models');
    }
};
