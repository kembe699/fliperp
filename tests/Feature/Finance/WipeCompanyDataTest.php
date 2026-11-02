<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// company:wipe clears one company's business data and leaves every other company alone.

function companyWithOpeningBalance(string $clientCode): array
{
    [$company, $branch] = createCompanyWithMainBranch(['client_code' => $clientCode]);
    seedChartOfAccounts($company);
    $admin = createUserWithRole('company_admin', $company, $branch);
    Sanctum::actingAs($admin, ['*']);
    test()->postJson('/api/v1/chart-of-accounts', [
        'code' => '1010', 'name' => 'Petty Cash', 'type' => 'asset',
        'opening_balance' => 500, 'opening_balance_date' => '2026-01-01',
    ])->assertCreated();

    return [$company, $admin];
}

function journalLineCount(int $companyId): int
{
    return DB::table('journal_entry_lines')
        ->whereIn('journal_entry_id', JournalEntry::withoutGlobalScopes()->where('company_id', $companyId)->select('id'))
        ->count();
}

it('only counts on a dry run', function () {
    [$company] = companyWithOpeningBalance('W001');

    $this->artisan('company:wipe', ['client_code' => 'W001'])->assertSuccessful();

    expect(JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(1)
        ->and(journalLineCount($company->id))->toBe(2);
});

it('deletes the company\'s entries and lines, keeps its users and accounts, and leaves other companies alone', function () {
    [$target, $targetAdmin] = companyWithOpeningBalance('W002');
    [$other] = companyWithOpeningBalance('W003');

    $this->artisan('company:wipe', ['client_code' => 'W002', '--confirm' => true])->assertSuccessful();

    $petty = ChartOfAccount::withoutGlobalScopes()->where('company_id', $target->id)->where('code', '1010')->first();
    expect(JournalEntry::withoutGlobalScopes()->where('company_id', $target->id)->count())->toBe(0)
        ->and(journalLineCount($target->id))->toBe(0)
        ->and($petty)->not->toBeNull()
        ->and((float) $petty->opening_balance)->toEqual(0.0)
        ->and($petty->opening_balance_entry_id)->toBeNull()
        ->and(DB::table('users')->where('id', $targetAdmin->id)->exists())->toBeTrue()
        ->and(JournalEntry::withoutGlobalScopes()->where('company_id', $other->id)->count())->toBe(1)
        ->and(journalLineCount($other->id))->toBe(2);
});

it('refuses the platform company and unknown codes', function () {
    createCompanyWithMainBranch(['client_code' => 'PLAT', 'is_platform' => true]);

    $this->artisan('company:wipe', ['client_code' => 'PLAT', '--confirm' => true])->assertFailed();
    $this->artisan('company:wipe', ['client_code' => 'NOPE'])->assertFailed();
});
