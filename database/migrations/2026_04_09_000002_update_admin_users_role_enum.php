<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE admin_users MODIFY COLUMN role ENUM('super_admin','admin','editor','order_manager') NOT NULL DEFAULT 'editor'");
    }

    public function down(): void
    {
        // Set any new roles back to 'editor' before shrinking the ENUM
        DB::statement("UPDATE admin_users SET role = 'editor' WHERE role NOT IN ('super_admin','editor')");
        DB::statement("ALTER TABLE admin_users MODIFY COLUMN role ENUM('super_admin','editor') NOT NULL DEFAULT 'editor'");
    }
};
