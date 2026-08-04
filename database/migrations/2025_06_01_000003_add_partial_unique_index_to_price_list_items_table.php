<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The composite unique on (price_list_id, product_id, product_variant_id)
        // doesn't catch duplicate no-variant rows in Postgres, since NULL is
        // never equal to NULL. Same fix as stock_levels.
        DB::statement(
            'CREATE UNIQUE INDEX price_list_items_no_variant_unique
                ON price_list_items (price_list_id, product_id)
                WHERE product_variant_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS price_list_items_no_variant_unique');
    }
};
