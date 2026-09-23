<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm role 'employee' (nhân viên admin, quyền hạn do EmployeePermission quyết
 * định — xem migration create_employee_permissions_table). `role` vốn là enum
 * cứng — đổi bằng add-copy-drop-rename (portable mysql/sqlite, không cần
 * doctrine/dbal), giống cách đã làm với coupons.type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_v2')->default('customer')->after('role');
        });

        DB::table('users')->update(['role_v2' => DB::raw('role')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_v2', 'role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
