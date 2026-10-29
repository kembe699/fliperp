<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One rate per currency per date (SSP can move daily). `rate` is how many
     * units of currency_code equal 1 unit of the company's base currency
     * (companies.currency_code) — "1 USD = 7,800 SSP" is stored as 7800. A
     * transaction uses the latest row on or before its own date.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('rate', 24, 10);
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'currency_code', 'effective_date']);
            $table->index(['company_id', 'currency_code', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
