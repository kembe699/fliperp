<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('contract_type');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('base_salary', 15, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('document_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
