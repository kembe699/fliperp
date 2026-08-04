<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->string('asset_code');
            $table->string('name');
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 2);
            $table->decimal('current_value', 15, 2);
            $table->enum('status', ['in_use', 'in_storage', 'under_maintenance', 'disposed'])->default('in_use');
            $table->foreignId('assigned_to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'asset_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
