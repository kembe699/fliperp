<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('goods_received_notes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->decimal('quantity_received', 15, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->enum('condition', ['good', 'damaged', 'rejected'])->default('good');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
    }
};
