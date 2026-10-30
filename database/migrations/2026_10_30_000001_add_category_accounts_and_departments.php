<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories carry the accounts their products post to (revenue, cost of
 * sales, inventory), products belong to a department, and journal lines
 * carry that department so the finance reports can be read per department.
 * Each is nullable: a category without an account falls back to the default
 * chart code, and existing products keep posting exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('revenue_account_id')->nullable()->after('sort_order')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->after('revenue_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->after('cogs_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('category_id')->constrained('departments')->nullOnDelete();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_account_id');
            $table->dropConstrainedForeignId('cogs_account_id');
            $table->dropConstrainedForeignId('revenue_account_id');
        });
    }
};
