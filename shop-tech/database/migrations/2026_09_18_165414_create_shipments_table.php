<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->string('provider')->default('ghn');
            $table->string('tracking_number')->nullable()->index();
            $table->string('client_order_code')->nullable();
            $table->unsignedTinyInteger('service_type_id')->nullable();
            $table->decimal('fee', 15, 2)->nullable();
            $table->unsignedInteger('weight_gram')->nullable();
            $table->unsignedInteger('length_cm')->nullable();
            $table->unsignedInteger('width_cm')->nullable();
            $table->unsignedInteger('height_cm')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('expected_delivery_time')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
