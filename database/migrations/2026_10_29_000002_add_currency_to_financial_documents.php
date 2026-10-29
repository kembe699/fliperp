<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const DOCUMENT_TABLES = ['invoices', 'customer_payments', 'supplier_bills', 'supplier_payments', 'journal_entries'];

    /**
     * Each document records the currency it was written in and the rate used
     * (units of that currency per 1 base unit — see exchange_rates). Journal
     * lines keep `debit`/`credit` in the base currency, which every report
     * reads, and gain `transaction_debit`/`transaction_credit` in the entry's
     * own currency. Existing rows are all base-currency, so they get the
     * company's currency at rate 1 and transaction amounts equal to base.
     */
    public function up(): void
    {
        foreach (self::DOCUMENT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('currency_code', 3)->nullable();
                $blueprint->decimal('exchange_rate', 24, 10)->default(1);
            });

            DB::statement("UPDATE {$table} SET currency_code = companies.currency_code FROM companies WHERE companies.id = {$table}.company_id");
        }

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->decimal('transaction_debit', 15, 2)->default(0);
            $table->decimal('transaction_credit', 15, 2)->default(0);
        });

        DB::statement('UPDATE journal_entry_lines SET transaction_debit = debit, transaction_credit = credit');
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['transaction_debit', 'transaction_credit']);
        });

        foreach (self::DOCUMENT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['currency_code', 'exchange_rate']);
            });
        }
    }
};
