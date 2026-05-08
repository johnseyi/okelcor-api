<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eu_declarations', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('admin_acknowledged_by');
            $table->unsignedInteger('reminder_count')->default(0)->after('last_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('eu_declarations', function (Blueprint $table) {
            $table->dropColumn(['last_reminded_at', 'reminder_count']);
        });
    }
};
