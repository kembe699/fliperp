<?php

use App\Services\Finance\ChartOfAccountService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Companies created before the default chart of accounts was seeded on
     * creation have no accounts until someone presses "seed defaults", and
     * every posting (invoices, payments, payroll) and the cash-flow report
     * fail on the missing codes. Adds only the default codes a company lacks;
     * existing accounts — renamed or not — are left as they are.
     */
    public function up(): void
    {
        $service = app(ChartOfAccountService::class);

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $service->seedDefaultsFor((int) $companyId);
        }
    }

    public function down(): void
    {
        // Accounts may already carry postings; nothing is removed.
    }
};
