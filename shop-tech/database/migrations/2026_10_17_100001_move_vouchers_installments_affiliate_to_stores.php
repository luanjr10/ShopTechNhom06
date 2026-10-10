<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $firstStore = DB::table('stores')->orderBy('id')->value('id');

        // Voucher trước đây do sàn phát: giao cho gian hàng đầu tiên để giữ dữ liệu demo, seller tự chỉnh/xoá sau.
        if ($firstStore) {
            DB::table('coupons')->whereNull('store_id')->update(['store_id' => $firstStore]);
        }

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('store_shipping_subsidy', 15, 2)->default(0);
        });

        Schema::create('store_installment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->decimal('min_order', 15, 2)->default(3000000);
            $table->json('terms');
            $table->timestamps();
        });

        Schema::table('installment_plans', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('seller_order_id')->nullable()->after('store_id')->index();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->string('status', 20)->default('pending_approval')->change();
        });

        Schema::create('store_affiliate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->decimal('rate', 5, 2)->default(2);
            $table->unsignedSmallInteger('hold_days')->default(7);
            $table->unsignedInteger('min_withdrawal')->default(50000);
            $table->timestamps();
        });

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('referred_user_id')->constrained()->nullOnDelete();
        });

        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Gắn dữ liệu cũ (nếu có) vào gian hàng của đơn con tương ứng.
        foreach (DB::table('affiliate_commissions')->whereNull('store_id')->get(['id', 'seller_order_id']) as $row) {
            $storeId = DB::table('seller_orders')->where('id', $row->seller_order_id)->value('store_id');
            DB::table('affiliate_commissions')->where('id', $row->id)->update(['store_id' => $storeId]);
        }

        foreach (DB::table('installment_plans')->whereNull('store_id')->get(['id', 'order_id']) as $plan) {
            $sellerOrder = DB::table('seller_orders')->where('order_id', $plan->order_id)->orderBy('id')->first(['id', 'store_id']);
            if ($sellerOrder) {
                DB::table('installment_plans')->where('id', $plan->id)->update(['store_id' => $sellerOrder->store_id, 'seller_order_id' => $sellerOrder->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::dropIfExists('store_affiliate_settings');

        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropColumn(['seller_order_id', 'decided_at', 'decision_note']);
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::dropIfExists('store_installment_settings');

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn('store_shipping_subsidy');
        });
    }
};
