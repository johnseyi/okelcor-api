<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('display_name')->nullable()->after('last_name');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->boolean('must_change_password')->default(false)->after('last_login_ip');
            $table->boolean('is_active')->default(true)->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'display_name',
                'last_login_ip',
                'must_change_password',
                'is_active',
            ]);
        });
    }
};
