<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('me_project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit_of_measure')->nullable();
            $table->decimal('target_value', 15, 2);
            $table->decimal('baseline_value', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_indicators');
    }
};
