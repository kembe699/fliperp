<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('depreciation_method', ['straight_line', 'reducing_balance']);
            $table->unsignedInteger('useful_life_years');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
