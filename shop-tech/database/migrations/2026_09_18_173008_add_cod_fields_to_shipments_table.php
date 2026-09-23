<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thu hộ (COD) — số tiền GHN cần thu từ khách khi giao (chỉ >0 với
 * payment_method='cod', xem ShippingService::createShipmentForSellerOrder) và
 * thời điểm GHN đã chuyển khoản COD đó về cho platform (webhook Type='cod').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedInteger('cod_amount')->default(0)->after('fee');
            $table->timestamp('cod_transferred_at')->nullable()->after('cod_amount');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['cod_amount', 'cod_transferred_at']);
        });
    }
};
