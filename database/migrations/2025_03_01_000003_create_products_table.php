<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('cost_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            // No tax_rates table exists yet in this system; kept as a plain
            // nullable reference (no FK constraint) so it's ready to attach
            // once a tax rates module is introduced.
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('reorder_level', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('image_url')->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
