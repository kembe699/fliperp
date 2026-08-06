<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widen these to text: an encrypted value (base64 ciphertext + IV + MAC,
     * JSON-wrapped) runs well past a 255-char varchar even for a short
     * plaintext like a national ID. Raw DDL rather than Schema::change()
     * since this app doesn't have doctrine/dbal installed; varchar -> text
     * is a lossless widening in Postgres.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE employees ALTER COLUMN national_id TYPE text');
        DB::statement('ALTER TABLE employees ALTER COLUMN bank_account_number TYPE text');
        DB::statement('ALTER TABLE customers ALTER COLUMN tax_id TYPE text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE employees ALTER COLUMN national_id TYPE varchar(255)');
        DB::statement('ALTER TABLE employees ALTER COLUMN bank_account_number TYPE varchar(255)');
        DB::statement('ALTER TABLE customers ALTER COLUMN tax_id TYPE varchar(255)');
    }
};
