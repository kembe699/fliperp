<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres treats NULL as distinct in a composite unique constraint, so
     * (product_id, product_variant_id, warehouse_id) alone lets multiple
     * no-variant rows exist for the same product/warehouse. This partial
     * index closes that gap for the product_variant_id IS NULL case.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX stock_levels_product_warehouse_no_variant_unique
                ON stock_levels (product_id, warehouse_id)
                WHERE product_variant_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stock_levels_product_warehouse_no_variant_unique');
    }
};
