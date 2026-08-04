<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfills chart-of-accounts code 5400 "Cash Short/Over" for every
     * company that already exists, not just the demo one — needed so
     * CashDrawerService::close() can post a variance journal entry for
     * companies that registered (and seeded their own chart of accounts)
     * before this account existed. Uses query-builder inserts rather than
     * the Eloquent models since migrations should be resilient to the
     * models' shape changing later.
     */
    public function up(): void
    {
        $companyIds = DB::table('companies')->pluck('id');
        $now = now();

        foreach ($companyIds as $companyId) {
            $exists = DB::table('chart_of_accounts')
                ->where('company_id', $companyId)
                ->where('code', '5400')
                ->exists();

            if (! $exists) {
                DB::table('chart_of_accounts')->insert([
                    'company_id' => $companyId,
                    'code' => '5400',
                    'name' => 'Cash Short/Over',
                    'type' => 'expense',
                    'parent_id' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Intentionally not reversible — dropping the account could orphan a
     * posted journal entry line if any variance was recorded against it
     * after this migration ran.
     */
    public function down(): void
    {
    }
};
