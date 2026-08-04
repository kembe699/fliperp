<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 15, 2)->default(0);
            $table->decimal('quantity_reserved', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
