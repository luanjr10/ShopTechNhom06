<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->enum('method', ['cod', 'momo', 'vnpay', 'onepay', 'sepay'])->default('cod')->after('amount');
            $table->string('payout_reference')->nullable()->after('note');
            $table->timestamp('paid_at')->nullable()->after('payout_reference');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn(['method', 'payout_reference', 'paid_at']);
        });
    }
};
