<?php

use App\Services\Finance\SystemAccountService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Which account each automatic posting uses (cash, receivables, payables,
 * salaries, tax, revenue, FX ...) becomes a per-company setting instead of a
 * fixed account code. Every existing company is mapped to the account with
 * the code it uses today, so its postings don't change.
 *
 * Also adds is_postable: a heading account groups others and takes no
 * journal lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_postable')->default(true)->after('is_active');
        });

        Schema::create('company_system_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('function', 50);
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'function']);
        });

        $now = now();
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $byCode = DB::table('chart_of_accounts')->where('company_id', $companyId)->pluck('id', 'code');
            $rows = [];
            foreach (SystemAccountService::FUNCTIONS as $function => $definition) {
                if (isset($byCode[$definition['code']])) {
                    $rows[] = ['company_id' => $companyId, 'function' => $function, 'account_id' => $byCode[$definition['code']], 'created_at' => $now, 'updated_at' => $now];
                }
            }
            if ($rows) {
                DB::table('company_system_accounts')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_system_accounts');
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('is_postable');
        });
    }
};
