<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests for quotation: what is needed (items), who is asked (suppliers),
 * what each supplier quoted per item, and the purchase order it became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number');
            $table->string('title');
            $table->date('issue_date');
            $table->date('response_due_date')->nullable();
            // draft → sent → converted (to a purchase order); or cancelled.
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('selected_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'reference_number']);
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('rfq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->unsignedInteger('delivery_days')->nullable();
            $table->text('quote_notes')->nullable();
            $table->timestamps();
            $table->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('rfq_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 15, 2);
            $table->timestamps();
            $table->unique(['rfq_supplier_id', 'rfq_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_quotes');
        Schema::dropIfExists('rfq_suppliers');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
