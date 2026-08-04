<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('me_project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed'])->default('not_started');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_activities');
    }
};
