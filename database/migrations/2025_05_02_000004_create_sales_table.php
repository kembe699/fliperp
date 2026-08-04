<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_drawer_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->string('reference_number');
            $table->enum('sale_type', ['pos', 'dine_in', 'takeaway', 'delivery'])->default('pos');
            $table->enum('status', ['held', 'completed', 'voided', 'refunded'])->default('held');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->foreignId('served_by')->constrained('users')->restrictOnDelete();
            $table->date('sale_date');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
