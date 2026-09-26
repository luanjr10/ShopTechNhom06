<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_code')->nullable()->after('payment_method');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_code');
            $table->string('payment_ref')->nullable()->after('discount_amount');
            $table->timestamp('paid_at')->nullable()->after('payment_ref');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_code', 'discount_amount', 'payment_ref', 'paid_at']);
        });
    }
};
