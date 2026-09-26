<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('province_id_ghn')->nullable()->after('ward_name');
            $table->string('province_name_ghn')->nullable()->after('province_id_ghn');
            $table->unsignedInteger('district_id')->nullable()->after('province_name_ghn');
            $table->string('district_name')->nullable()->after('district_id');
            $table->string('ward_code_ghn')->nullable()->after('district_name');
            $table->string('ward_name_ghn')->nullable()->after('ward_code_ghn');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn([
                'province_id_ghn', 'province_name_ghn', 'district_id', 'district_name',
                'ward_code_ghn', 'ward_name_ghn',
            ]);
        });
    }
};
