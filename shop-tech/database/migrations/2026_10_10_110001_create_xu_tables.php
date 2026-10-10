<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('xu_balance')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('xu_used')->default(0);
        });

        Schema::create('xu_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type', 'reference_type', 'reference_id'], 'xu_transactions_unique_ref');
        });

        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->unsignedInteger('streak');
            $table->unsignedInteger('xu_earned');
            $table->timestamps();

            $table->unique(['user_id', 'checkin_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
        Schema::dropIfExists('xu_transactions');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('xu_used');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('xu_balance');
        });
    }
};
