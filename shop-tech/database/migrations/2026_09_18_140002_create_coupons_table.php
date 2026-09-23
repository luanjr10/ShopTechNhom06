<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percent', 'fixed'])->default('percent');
            $table->decimal('value', 15, 2); // % nếu type=percent, số tiền nếu type=fixed
            $table->decimal('max_discount', 15, 2)->nullable(); // chặn trên khi type=percent
            $table->decimal('min_order_amount', 15, 2)->default(0);
            $table->unsignedInteger('usage_limit')->nullable(); // null = không giới hạn
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Vài mã demo để test — không hard-code ở FE, luôn qua API validate.
        DB::table('coupons')->insert([
            [
                'code' => 'GIAM10',
                'type' => 'percent',
                'value' => 10,
                'max_discount' => 100000,
                'min_order_amount' => 200000,
                'usage_limit' => null,
                'used_count' => 0,
                'expires_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'GIAM50K',
                'type' => 'fixed',
                'value' => 50000,
                'max_discount' => null,
                'min_order_amount' => 300000,
                'usage_limit' => null,
                'used_count' => 0,
                'expires_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
