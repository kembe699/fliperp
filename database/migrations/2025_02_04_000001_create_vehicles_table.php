<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('registration_number');
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('capacity')->nullable();
            $table->enum('status', ['available', 'in_transit', 'maintenance'])->default('available');
            $table->timestamps();

            $table->unique(['company_id', 'registration_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
