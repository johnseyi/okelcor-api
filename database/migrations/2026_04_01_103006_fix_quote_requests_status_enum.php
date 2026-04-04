<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename any existing 'reviewing' rows before altering the ENUM
        DB::table('quote_requests')
            ->where('status', 'reviewing')
            ->update(['status' => 'reviewed']);

        DB::statement("ALTER TABLE quote_requests MODIFY COLUMN status ENUM('new', 'reviewed', 'quoted', 'closed') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        DB::table('quote_requests')
            ->where('status', 'reviewed')
            ->update(['status' => 'reviewing']);

        DB::statement("ALTER TABLE quote_requests MODIFY COLUMN status ENUM('new', 'reviewing', 'quoted', 'closed') NOT NULL DEFAULT 'new'");
    }
};
