<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_affiliates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->unsignedBigInteger('store_id')->index();
            $table->decimal('rate', 5, 2);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('affiliate_referrer_id')->nullable()->index();
            $table->decimal('affiliate_rate', 5, 2)->nullable();
        });

        // Một đơn con có thể có nhiều người giới thiệu (mỗi sản phẩm một link khác nhau).
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->unique(['seller_order_id', 'referrer_id'], 'affiliate_commissions_order_referrer_unique');
        });
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropUnique('affiliate_commissions_seller_order_id_unique');
        });

        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            $table->string('payout_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            $table->dropColumn('payout_reference');
        });

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->unique('seller_order_id');
        });
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropUnique('affiliate_commissions_order_referrer_unique');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['affiliate_referrer_id', 'affiliate_rate']);
        });

        Schema::dropIfExists('product_affiliates');
    }
};
