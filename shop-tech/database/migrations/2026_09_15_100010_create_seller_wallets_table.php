<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->unique()->constrained('seller_profiles')->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0);            // tổng
            $table->decimal('pending_balance', 15, 2)->default(0);    // đơn chưa hoàn thành
            $table->decimal('withdrawable_balance', 15, 2)->default(0); // đã hoàn thành, rút được
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_wallets');
    }
};
