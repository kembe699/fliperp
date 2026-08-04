<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('location')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tracking');
    }
};
