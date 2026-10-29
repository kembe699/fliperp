<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** EUR and GBP, which donors (and so SOFs) are commonly awarded in. */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('currencies')) {
            return;
        }

        foreach ([['EUR', 'Euro', '€'], ['GBP', 'British Pound', '£']] as [$code, $name, $symbol]) {
            DB::table('currencies')->insertOrIgnore(['code' => $code, 'name' => $name, 'symbol' => $symbol, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Left in place: documents and rates may already use them.
    }
};
