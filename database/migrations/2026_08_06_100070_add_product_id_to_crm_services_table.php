<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quotations/invoices are built entirely on real Product rows
     * (quotation_items.product_id is NOT NULL). Rather than fork that
     * battle-tested pipeline to also accept free-text lines, each CrmService
     * lazily gets a linked, non-stock-tracked Product (see
     * CrmService::ensureProduct()) the first time it's quoted — so CRM
     * quotations are ordinary quotations with zero special-cased logic.
     */
    public function up(): void
    {
        Schema::table('crm_services', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
