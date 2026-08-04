<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The stub notifications:table migration creates data as text — fine
     * for storing it, but the /notifications page needs to filter by
     * category (nested inside that JSON), which on Postgres requires an
     * actual json/jsonb column type for the -> path operator to work.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text');
    }
};
