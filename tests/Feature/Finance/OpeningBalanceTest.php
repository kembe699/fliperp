<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Laravel\Sanctum\Sanctum;

// Opening balances on the chart of accounts (ChartOfAccountService::syncOpeningBalance):
// posted to the ledger against 3900 Opening Balance Equity.

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    Sanctum::actingAs($this->admin, ['*']);
});

function ledgerNet(int $companyId, string $code): float
{
    $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();
    if (! $account) {
        return 0.0;
    }
    $t = $account->journalEntryLines()
        ->whereHas('journalEntry', fn ($q) => $q->whereIn('status', JournalEntry::LEDGER_STATUSES))
        ->selectRaw('COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')->first();

    return round((float) $t->d - (float) $t->c, 2);
}

it('posts an asset opening balance as a debit against Opening Balance Equity', function () {
    $res = $this->postJson('/api/v1/chart-of-accounts', [
        'code' => '1010', 'name' => 'Petty Cash', 'type' => 'asset',
        'opening_balance' => 1500, 'opening_balance_date' => '2026-01-01',
    ])->assertCreated()->json('data');

    expect($res['opening_balance'])->toEqual(1500.0)
        ->and($res['opening_balance_date'])->toBe('2026-01-01')
        ->and(ledgerNet($this->company->id, '1010'))->toEqual(1500.0)
        ->and(ledgerNet($this->company->id, '3900'))->toEqual(-1500.0);

    $entry = JournalEntry::withoutGlobalScopes()->find($res['opening_balance_entry_id']);
    expect($entry->status)->toBe('posted')
        ->and($entry->source_module)->toBe('opening_balance')
        ->and($entry->entry_date->toDateString())->toBe('2026-01-01');
});

it('posts a liability opening balance as a credit', function () {
    $ap = $this->accounts['2000'] ?? ChartOfAccount::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', '2000')->first();
    $this->putJson("/api/v1/chart-of-accounts/{$ap->id}", ['opening_balance' => 800, 'opening_balance_date' => '2026-01-01'])->assertOk();

    expect(ledgerNet($this->company->id, '2000'))->toEqual(-800.0)
        ->and(ledgerNet($this->company->id, '3900'))->toEqual(800.0);
});

it('changing an opening balance reverses the old entry and leaves only the new amount', function () {
    $id = $this->postJson('/api/v1/chart-of-accounts', [
        'code' => '1020', 'name' => 'Bank - Equity Bank', 'type' => 'asset', 'opening_balance' => 1000,
    ])->assertCreated()->json('data.id');
    $first = ChartOfAccount::withoutGlobalScopes()->find($id)->opening_balance_entry_id;

    $this->putJson("/api/v1/chart-of-accounts/{$id}", ['opening_balance' => 2500])->assertOk();

    expect(ledgerNet($this->company->id, '1020'))->toEqual(2500.0)
        ->and(JournalEntry::withoutGlobalScopes()->find($first)->status)->toBe('reversed');

    // Unchanged amount: nothing new is posted.
    $count = JournalEntry::withoutGlobalScopes()->where('company_id', $this->company->id)->count();
    $this->putJson("/api/v1/chart-of-accounts/{$id}", ['opening_balance' => 2500, 'name' => 'Bank - Equity Bank (main)'])->assertOk();
    expect(JournalEntry::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe($count);

    // Cleared: back to zero on the ledger.
    $this->putJson("/api/v1/chart-of-accounts/{$id}", ['opening_balance' => 0])->assertOk();
    expect(ledgerNet($this->company->id, '1020'))->toEqual(0.0);
});

it('keeps the trial balance balanced and shows each account balance', function () {
    $this->postJson('/api/v1/chart-of-accounts', ['code' => '1030', 'name' => 'Bank - KCB', 'type' => 'asset', 'opening_balance' => 4000, 'opening_balance_date' => '2026-01-01'])->assertCreated();
    $this->postJson('/api/v1/chart-of-accounts', ['code' => '2050', 'name' => 'Loan', 'type' => 'liability', 'opening_balance' => 1000, 'opening_balance_date' => '2026-01-01'])->assertCreated();

    $tb = $this->getJson('/api/v1/reports/trial-balance?from=2026-01-01&to=2026-12-31')->assertOk()->json('data');
    expect(round($tb['total_debit'], 2))->toEqual(round($tb['total_credit'], 2));

    $list = collect($this->getJson('/api/v1/chart-of-accounts?per_page=200')->assertOk()->json('data'))->keyBy('code');
    expect($list['1030']['balance'])->toEqual(4000.0)
        ->and($list['2050']['balance'])->toEqual(1000.0)
        ->and($list['3900']['balance'])->toEqual(3000.0);
});

it('refuses an opening balance on Opening Balance Equity itself', function () {
    $this->postJson('/api/v1/chart-of-accounts', ['code' => '1040', 'name' => 'Cash Box', 'type' => 'asset', 'opening_balance' => 10])->assertCreated();
    $equity = ChartOfAccount::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', '3900')->first();
    $this->putJson("/api/v1/chart-of-accounts/{$equity->id}", ['opening_balance' => 50])->assertStatus(422);
});

it('includes Opening Balance Equity in the default chart', function () {
    $codes = collect($this->postJson('/api/v1/chart-of-accounts/seed')->assertOk()->json('data'))->pluck('code');
    expect($codes)->toContain('3900');
});
