<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('sepay_invoice_id')->nullable()->after('payment_ref');
            $table->string('sepay_transaction_id')->nullable()->after('sepay_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['sepay_invoice_id', 'sepay_transaction_id']);
        });
    }
};
