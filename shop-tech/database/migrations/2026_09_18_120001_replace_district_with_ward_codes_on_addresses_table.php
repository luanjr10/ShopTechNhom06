<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('province_code')->nullable()->after('phone');
            $table->string('province_name')->nullable()->after('province_code');
            $table->unsignedInteger('ward_code')->nullable()->after('province_name');
            $table->string('ward_name')->nullable()->after('ward_code');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province', 'district', 'ward']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('province')->nullable()->after('phone');
            $table->string('district')->nullable()->after('province');
            $table->string('ward')->nullable()->after('district');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'province_name', 'ward_code', 'ward_name']);
        });
    }
};
