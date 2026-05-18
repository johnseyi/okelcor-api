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
        Schema::table('trade_documents', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('sent_at');
            $table->foreignId('superseded_by_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete()
                ->after('superseded_at');
            $table->text('supersede_reason')->nullable()->after('superseded_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('trade_documents', function (Blueprint $table) {
            $table->dropForeign(['superseded_by_id']);
            $table->dropColumn(['superseded_at', 'superseded_by_id', 'supersede_reason']);
        });
    }
};
