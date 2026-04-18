<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->enum('customer_type', ['b2c', 'b2b'])->default('b2c');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('company_name')->nullable();
            $table->string('vat_number')->nullable();
            $table->boolean('vat_verified')->default(false);
            $table->string('industry')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('must_reset_password')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('imported_from_wix')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
