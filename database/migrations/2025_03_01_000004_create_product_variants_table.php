<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->decimal('price_adjustment', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
