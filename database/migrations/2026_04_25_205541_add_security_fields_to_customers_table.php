<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'banned', 'locked'])
                  ->default('active')->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->string('last_login_location', 100)->nullable()->after('last_login_ip');
            $table->unsignedInteger('failed_login_count')->default(0)->after('last_login_location');
            $table->text('admin_notes')->nullable()->after('failed_login_count');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'last_login_at', 'last_login_ip',
                'last_login_location', 'failed_login_count', 'admin_notes',
            ]);
        });
    }
};
