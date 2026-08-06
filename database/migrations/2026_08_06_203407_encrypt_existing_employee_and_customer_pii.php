<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $targets = [
        'employees' => ['national_id', 'bank_account_number'],
        'customers' => ['tax_id'],
    ];

    /**
     * One-time backfill: Employee/Customer models now cast these columns as
     * 'encrypted', so any plaintext value already in the database would
     * throw a DecryptException the next time it's read through the model.
     * Encrypts in place via the Crypt facade directly (bypassing Eloquent
     * casts entirely) so this runs correctly regardless of migration order
     * relative to the model change. Idempotent: a value that already
     * decrypts successfully is left untouched, so re-running this is safe.
     */
    public function up(): void
    {
        foreach ($this->targets as $table => $columns) {
            DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column};

                        if ($value === null || $value === '') {
                            continue;
                        }

                        try {
                            Crypt::decryptString($value);
                            // Already encrypted — leave as-is.
                        } catch (DecryptException) {
                            $updates[$column] = Crypt::encryptString($value);
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations: decrypt back to plaintext.
     */
    public function down(): void
    {
        foreach ($this->targets as $table => $columns) {
            DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column};

                        if ($value === null || $value === '') {
                            continue;
                        }

                        try {
                            $updates[$column] = Crypt::decryptString($value);
                        } catch (DecryptException) {
                            // Already plaintext — leave as-is.
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }
};
