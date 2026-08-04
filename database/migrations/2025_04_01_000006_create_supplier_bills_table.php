<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_received_notes')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number');
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue'])->default('unpaid');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bills');
    }
};
