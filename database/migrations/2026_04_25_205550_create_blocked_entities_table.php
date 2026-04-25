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
        Schema::create('blocked_entities', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['email', 'ip']);
            $table->string('value', 255);
            $table->string('reason', 300)->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_entities');
    }
};
