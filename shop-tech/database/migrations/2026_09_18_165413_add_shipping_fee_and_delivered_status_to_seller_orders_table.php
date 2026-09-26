<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('shipping_fee', 15, 2)->default(0)->after('subtotal');
        });

        DB::statement("ALTER TABLE seller_orders MODIFY status ENUM('pending','confirmed','shipping','delivered','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE seller_orders SET status = 'completed' WHERE status = 'delivered'");
        DB::statement("ALTER TABLE seller_orders MODIFY status ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
    }
};
