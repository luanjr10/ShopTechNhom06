<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('ghn_province_id')->nullable()->after('shipping_address');
            $table->string('ghn_province_name')->nullable()->after('ghn_province_id');
            $table->unsignedInteger('ghn_district_id')->nullable()->after('ghn_province_name');
            $table->string('ghn_district_name')->nullable()->after('ghn_district_id');
            $table->string('ghn_ward_code')->nullable()->after('ghn_district_name');
            $table->string('ghn_ward_name')->nullable()->after('ghn_ward_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ghn_province_id', 'ghn_province_name', 'ghn_district_id',
                'ghn_district_name', 'ghn_ward_code', 'ghn_ward_name',
            ]);
        });
    }
};
