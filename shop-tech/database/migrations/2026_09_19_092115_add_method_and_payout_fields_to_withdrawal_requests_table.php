<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller chọn kênh nhận tiền — cùng danh sách payment_method đã có ở checkout
 * (orders.payment_method: cod/momo/vnpay/onepay/sepay), để đồng bộ trải
 * nghiệm. `payout_reference`/`paid_at` lưu lại "giao dịch" giải ngân qua cổng
 * online (xem PayoutService — SANDBOX, các cổng này chỉ có API pay-in thật
 * trong dự án, không có API giải ngân thật, nên đây là bản mô phỏng đối với
 * momo/vnpay/onepay/sepay; riêng 'cod' vẫn là chuyển khoản tay như cũ).
 */
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
