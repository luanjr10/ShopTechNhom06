<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_in_models', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('trade_in_requests', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->index();
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('store_discount', 15, 2)->default(0);
        });

        // Bảng giá mẫu trước đây do sàn đặt: giao cho gian hàng đầu tiên để có dữ liệu demo, seller tự chỉnh sau.
        $firstStore = DB::table('stores')->orderBy('id')->value('id');
        if ($firstStore) {
            DB::table('trade_in_models')->whereNull('store_id')->update(['store_id' => $firstStore]);
        }
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn('store_discount');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('store_id');
        });

        Schema::table('trade_in_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::table('trade_in_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
