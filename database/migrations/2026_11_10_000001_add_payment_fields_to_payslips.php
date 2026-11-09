<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salaries are paid employee by employee: each payslip records when it was
 * paid, by whom, how, from which cash/bank account, and the journal entry
 * that moved the money out of Net Salaries Payable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_type_id')->nullable()->constrained('payment_types')->nullOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->foreignId('payment_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_journal_entry_id');
            $table->dropConstrainedForeignId('payment_account_id');
            $table->dropConstrainedForeignId('payment_type_id');
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['paid_at', 'payment_reference']);
        });
    }
};
