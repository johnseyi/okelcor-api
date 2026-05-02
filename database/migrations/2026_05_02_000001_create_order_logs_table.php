<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('order_ref', 30);
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('admin_user_email', 255)->nullable();
            $table->enum('action', [
                'status_changed',
                'cancelled',
                'deleted',
                'tracking_updated',
                'payment_status_changed',
            ]);
            $table->string('old_value', 100)->nullable();
            $table->string('new_value', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->index('order_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};
