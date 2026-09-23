<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phân quyền theo module cho nhân viên (role=employee) — admin (role=admin)
 * luôn có full quyền, KHÔNG cần dòng ở đây (xem User::hasModulePermission()).
 * Nhân viên mới tạo chưa có dòng nào -> mặc định KHÔNG có quyền gì (an toàn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_permissions');
    }
};
