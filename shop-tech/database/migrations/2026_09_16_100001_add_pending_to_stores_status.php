<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Gian hàng mới do seller tạo phải chờ admin duyệt -> bổ sung trạng thái 'pending'.
    public function up(): void
    {
        DB::statement("ALTER TABLE stores MODIFY COLUMN status ENUM('active','inactive','pending') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stores MODIFY COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    }
};
