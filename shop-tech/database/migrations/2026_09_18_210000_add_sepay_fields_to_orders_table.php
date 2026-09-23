<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm field phục vụ đối soát SePay (mã QR checkout + mã giao dịch ngân hàng
 * trả về qua webhook). OnePay không cần field mới vì đã có payment_ref.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Mã QR SePay trả về (id checkout) — FE redirect tới URL này để khách quét.
            // Lưu để đối soát nếu cần tái tạo QR.
            $table->string('sepay_invoice_id')->nullable()->after('payment_ref');
            // Mã giao dịch thực tế SePay gửi trong webhook (idempotency + tra cứu).
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
