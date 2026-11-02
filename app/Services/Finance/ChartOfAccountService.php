<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChartOfAccountService
{
    /**
     * Codes 1000, 1100, 1200, 1900, 2000, 2100, 2200, 2300, 4000, 5000, 5100,
     * 5300, 5400 and 5500 are relied on by code in InvoiceService,
     * PayrollRunService, DepreciationService, the procurement services
     * (GrnService, SupplierPaymentService), the POS services (SaleService,
     * CashDrawerService, CustomerPaymentService) and, for realised FX
     * gain/loss, ExchangeRateService, when posting journal entries —
     * matches database/seeders/ChartOfAccountsSeeder.php (which only seeds the
     * one demo company). 3000 and 5200 aren't relied on by any service but
     * round out a minimally usable ledger.
     */
    protected const DEFAULTS = [
        ['1000', 'Cash and Bank', 'asset'],
        ['1100', 'Accounts Receivable', 'asset'],
        ['1200', 'Inventory', 'asset'],
        ['1900', 'Accumulated Depreciation', 'asset'],
        ['2000', 'Accounts Payable', 'liability'],
        ['2100', 'Statutory Deductions Payable', 'liability'],
        ['2200', 'Net Salaries Payable', 'liability'],
        ['2300', 'Tax Payable', 'liability'],
        ['3000', "Owner's Equity", 'equity'],
        ['3900', 'Opening Balance Equity', 'equity'],
        ['4000', 'Sales Revenue', 'revenue'],
        ['5000', 'Salaries Expense', 'expense'],
        ['5100', 'Depreciation Expense', 'expense'],
        ['5200', 'General Operating Expenses', 'expense'],
        ['5300', 'Cost of Goods Sold', 'expense'],
        ['5400', 'Cash Short/Over', 'expense'],
        ['5500', 'Realised FX Gain/Loss', 'expense'],
    ];

    public function __construct(protected JournalEntryService $journalEntryService) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $page = ChartOfAccount::query()->latest()->paginate($perPage);
        $this->attachBalances($page->getCollection());

        return $page;
    }

    /**
     * Each account's ledger balance (posted and reversed entries, as every
     * report reads them), in the account's own direction: debits less credits
     * for assets and expenses, credits less debits for the rest.
     */
    public function attachBalances($accounts): void
    {
        $ids = collect($accounts)->pluck('id')->all();
        if (! $ids) {
            return;
        }
        $totals = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', JournalEntry::LEDGER_STATUSES)
            ->whereIn('journal_entry_lines.account_id', $ids)
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw('journal_entry_lines.account_id, COALESCE(SUM(journal_entry_lines.debit), 0) AS d, COALESCE(SUM(journal_entry_lines.credit), 0) AS c')
            ->get()->keyBy('account_id');
        foreach ($accounts as $account) {
            $row = $totals->get($account->id);
            $net = $row ? (float) $row->d - (float) $row->c : 0.0;
            $account->setAttribute('balance', round(in_array($account->type, ChartOfAccount::DEBIT_NORMAL_TYPES, true) ? $net : -$net, 2));
        }
    }

    /**
     * Inserts every default account whose code isn't already in use for this
     * company, leaving existing rows — including any the user has since
     * renamed or reclassified — untouched. Safe to call repeatedly.
     */
    public function seedDefaults(): Collection
    {
        $this->seedDefaultsFor(Auth::user()->company_id);

        return ChartOfAccount::query()->orderBy('code')->get();
    }

    /**
     * Same as seedDefaults() for any company, signed in or not — run when a
     * company is created, so invoicing, payments, payroll and the reports
     * work from day one instead of failing on a missing account code.
     */
    public function seedDefaultsFor(int $companyId): void
    {
        $existingCodes = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->pluck('code')->all();

        foreach (self::DEFAULTS as [$code, $name, $type]) {
            if (in_array($code, $existingCodes, true)) {
                continue;
            }

            ChartOfAccount::withoutGlobalScopes()->create([
                'company_id' => $companyId, 'code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true,
            ]);
        }
    }

    public function create(array $data): ChartOfAccount
    {
        return DB::transaction(function () use ($data) {
            $opening = $this->pullOpening($data);
            $account = ChartOfAccount::create($data);
            if ($opening) {
                $this->syncOpeningBalance($account, ...$opening);
            }

            return $account->fresh();
        });
    }

    public function update(ChartOfAccount $account, array $data): ChartOfAccount
    {
        return DB::transaction(function () use ($account, $data) {
            $opening = $this->pullOpening($data);
            $account->update($data);
            if ($opening) {
                $this->syncOpeningBalance($account, ...$opening);
            }

            return $account->fresh();
        });
    }

    /** Takes the opening-balance fields out of $data: [amount, date] when sent, else null. */
    protected function pullOpening(array &$data): ?array
    {
        if (! array_key_exists('opening_balance', $data)) {
            unset($data['opening_balance_date']);

            return null;
        }
        $opening = [round((float) ($data['opening_balance'] ?? 0), 2), $data['opening_balance_date'] ?? null];
        unset($data['opening_balance'], $data['opening_balance_date']);

        return $opening;
    }

    /**
     * Puts the account's opening balance on the ledger: one posted entry
     * against 3900 Opening Balance Equity (created when first needed), so the
     * general ledger, trial balance and balance sheet all include it and stay
     * balanced. A positive amount is a normal balance for the account type
     * (a debit for assets/expenses, a credit for the rest); negative is the
     * opposite. Changing it reverses the previous entry and posts the new one;
     * an unchanged amount and date post nothing.
     */
    public function syncOpeningBalance(ChartOfAccount $account, float $amount, ?string $date = null): void
    {
        $date = $date ?: ($account->opening_balance_date?->toDateString() ?? now()->toDateString());
        $current = $account->opening_balance_entry_id ? JournalEntry::query()->find($account->opening_balance_entry_id) : null;
        if (round((float) $account->opening_balance, 2) === $amount
            && $account->opening_balance_date?->toDateString() === $date
            && ($amount == 0.0 || ($current && $current->status === 'posted'))) {
            return;
        }
        if ($account->code === ChartOfAccount::OPENING_BALANCE_EQUITY_CODE && $amount != 0.0) {
            throw ValidationException::withMessages([
                'opening_balance' => ['Opening Balance Equity is the balancing account for opening balances and cannot have one of its own.'],
            ]);
        }

        if ($current && $current->status === 'posted') {
            $this->journalEntryService->reverse($current);
        }

        $entryId = null;
        if ($amount != 0.0) {
            $equity = $this->openingBalanceEquity($account->company_id);
            $debitNormal = in_array($account->type, ChartOfAccount::DEBIT_NORMAL_TYPES, true);
            $accountDebit = ($debitNormal xor $amount < 0);
            $value = abs($amount);
            $entry = $this->journalEntryService->postModuleEntry([
                'company_id' => $account->company_id,
                'reference_number' => 'OB-'.$account->code.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'entry_date' => $date,
                'description' => "Opening balance — {$account->code} {$account->name}",
                'source_module' => 'opening_balance',
                'source_id' => $account->id,
                'lines' => [
                    ['account_id' => $account->id, 'debit' => $accountDebit ? $value : 0, 'credit' => $accountDebit ? 0 : $value, 'description' => 'Opening balance'],
                    ['account_id' => $equity->id, 'debit' => $accountDebit ? 0 : $value, 'credit' => $accountDebit ? $value : 0, 'description' => "Opening balance — {$account->code} {$account->name}"],
                ],
            ]);
            $entryId = $entry->id;
        }

        $account->forceFill([
            'opening_balance' => $amount,
            'opening_balance_date' => $amount != 0.0 ? $date : null,
            'opening_balance_entry_id' => $entryId,
        ])->save();
    }

    protected function openingBalanceEquity(int $companyId): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'code' => ChartOfAccount::OPENING_BALANCE_EQUITY_CODE],
            ['name' => 'Opening Balance Equity', 'type' => 'equity', 'is_active' => true],
        );
    }

    public function delete(ChartOfAccount $account): void
    {
        $account->delete();
    }
}
