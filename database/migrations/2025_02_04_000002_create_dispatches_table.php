<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number');
            $table->enum('source_module', ['procurement', 'sales', 'transfer']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('dispatched_by')->constrained('users')->restrictOnDelete();
            $table->date('dispatch_date');
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->unique(['company_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};
