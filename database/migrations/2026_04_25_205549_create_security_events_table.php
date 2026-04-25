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
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'failed_login', 'suspicious_activity', 'new_registration',
                'password_reset', 'account_changes', 'account_lockout',
                'account_unlock', 'account_suspend', 'account_ban',
            ]);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('location', 100)->nullable();
            $table->text('description');
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
