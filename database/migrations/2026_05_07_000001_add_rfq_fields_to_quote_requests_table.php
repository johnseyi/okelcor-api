<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            // Contact person separate from full_name (e.g. purchasing manager)
            $table->string('contact_person', 150)->nullable()->after('full_name');

            // Company billing/registered address
            $table->string('company_address', 300)->nullable()->after('company_name');
            $table->string('company_city', 100)->nullable()->after('company_address');
            $table->string('company_postal_code', 30)->nullable()->after('company_city');

            // Tyre condition and used-tyre details
            $table->string('tyre_condition', 50)->nullable()->after('tyre_size');
            $table->string('used_tyre_grade', 50)->nullable()->after('tyre_condition');
            $table->text('used_tyre_notes')->nullable()->after('used_tyre_grade');

            // Multi-row tyre items — replaces single tyre_size/quantity for complex RFQs
            $table->json('tyre_items')->nullable()->after('quantity');

            // Preferred logistics / incoterms
            $table->string('incoterm', 10)->nullable()->after('delivery_timeline');
            $table->string('incoterm_type', 30)->nullable()->after('incoterm');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn([
                'contact_person',
                'company_address',
                'company_city',
                'company_postal_code',
                'tyre_condition',
                'used_tyre_grade',
                'used_tyre_notes',
                'tyre_items',
                'incoterm',
                'incoterm_type',
            ]);
        });
    }
};
