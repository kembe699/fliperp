<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract']);
            $table->enum('status', ['active', 'on_leave', 'terminated'])->default('active');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
