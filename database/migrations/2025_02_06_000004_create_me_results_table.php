<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('me_indicator_id')->constrained()->cascadeOnDelete();
            $table->string('reporting_period');
            $table->decimal('actual_value', 15, 2);
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_results');
    }
};
