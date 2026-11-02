<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account's opening balance (see ChartOfAccountService::syncOpeningBalance):
 * the amount, the date it is as at, and the posted journal entry that carries
 * it into the ledger against 3900 Opening Balance Equity — so every report that
 * reads the ledger includes it and the trial balance still balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 18, 2)->default(0)->after('is_active');
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
            $table->foreignId('opening_balance_entry_id')->nullable()->after('opening_balance_date')
                ->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opening_balance_entry_id');
            $table->dropColumn(['opening_balance', 'opening_balance_date']);
        });
    }
};
