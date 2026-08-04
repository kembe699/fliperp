<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['tax', 'pension', 'other']);
            $table->enum('calculation_type', ['percentage', 'fixed', 'bracket']);
            $table->jsonb('config');
            $table->string('country_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_deduction_rules');
    }
};
