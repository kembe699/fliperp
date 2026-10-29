<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account a manually entered bill is expensed to (Dr expense / Cr
     * Accounts Payable). Null on goods-received bills, which post to
     * Inventory instead, and on bills from before bills posted at all.
     */
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_account_id');
        });
    }
};
