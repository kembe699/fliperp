<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_bill_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            // No payment_types table exists yet in this system (same situation
            // as products.tax_rate_id); kept as a plain reference, no FK.
            $table->unsignedBigInteger('payment_type_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
