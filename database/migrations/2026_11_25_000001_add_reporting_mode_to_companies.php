<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * How a company's financial statements are presented:
 *
 *  - 'standard'  — Profit & Loss / Balance Sheet, as every company had them
 *                  (the default; every existing company stays on it);
 *  - 'nonprofit' — Statement of Income and Expenses / Statement of Financial
 *                  Position: not-for-profit labels, no cost-of-sales block,
 *                  accounts grouped under the chart's headings.
 *
 * reporting_options overrides the mode's defaults in config/reporting.php for
 * one company (e.g. {"show_cost_of_sales": true}).
 *
 * Generation 711 (client code G711), a not-for-profit, is the one company
 * switched to 'nonprofit' here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('reporting_mode', 20)->default('standard')->after('allow_self_approval');
            $table->json('reporting_options')->nullable()->after('reporting_mode');
        });

        DB::table('companies')->where('client_code', 'G711')->update(['reporting_mode' => 'nonprofit']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['reporting_mode', 'reporting_options']);
        });
    }
};
