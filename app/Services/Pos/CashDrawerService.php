<?php

namespace App\Services\Pos;

use App\Models\CashDrawerSession;
use App\Models\CashDrawerVarianceRecovery;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Notifications\CashDrawerVarianceFlagged;
use App\Services\Finance\JournalEntryService;
use App\Services\Notifications\NotificationRecipientResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The choke point for cash drawer sessions: enforces the one-open-session-
 * per-user-per-branch rule, computes expected_closing/variance on close, and
 * posts that variance to the ledger (see close()) — a shortage or overage is
 * a real change in how much cash the business actually has, and until it's
 * posted the Trial Balance only reflects recorded sales/payments, not what
 * was physically counted, so it can look "balanced" even when cash is
 * missing.
 */
class CashDrawerService
{
    public const CASH_ACCOUNT_CODE = '1000';

    public const CASH_SHORT_OVER_ACCOUNT_CODE = '5400';

    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected NotificationRecipientResolver $recipientResolver,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CashDrawerSession::query()
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('opened_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('opened_at', '<=', $to))
            ->latest('opened_at')
            ->paginate($perPage);
    }

    public function current(): ?CashDrawerSession
    {
        $session = CashDrawerSession::query()
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();

        if ($session) {
            // Not persisted — lets the close-shift screen show what to expect
            // in the till before the cashier has counted or entered anything,
            // by reusing the same figure close() will actually charge against.
            $session->setAttribute('expected_closing', $this->calculateExpectedClosing($session));
        }

        return $session;
    }

    protected function calculateExpectedClosing(CashDrawerSession $session): float
    {
        $saleIds = Sale::query()->where('cash_drawer_session_id', $session->id)->pluck('id');

        $cashCollected = (float) SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->whereHas('paymentType', fn ($query) => $query->where('type', 'cash'))
            ->sum('amount');

        return round((float) $session->opening_float + $cashCollected, 2);
    }

    public function open(array $data): CashDrawerSession
    {
        $user = Auth::user();
        $branchId = $data['branch_id'] ?? $user->branch_id;

        return DB::transaction(function () use ($data, $user, $branchId) {
            $existing = CashDrawerSession::query()
                ->where('user_id', $user->id)
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'cash_drawer_session' => ['You already have an open cash drawer session for this branch. Close it before opening a new one.'],
                ]);
            }

            return CashDrawerSession::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'opening_float' => round((float) $data['opening_float'], 2),
                'opened_at' => now(),
                'status' => 'open',
            ]);
        });
    }

    public function close(CashDrawerSession $session, array $data): CashDrawerSession
    {
        if ($session->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only an open cash drawer session can be closed.'],
            ]);
        }

        $closed = DB::transaction(function () use ($session, $data) {
            $session = CashDrawerSession::query()->lockForUpdate()->findOrFail($session->id);

            // Re-check after the lock, not just before the transaction: two
            // concurrent close requests (a double-click, or a slow request
            // retried) would otherwise both pass the earlier check, then the
            // second would block on the lock and, once through, silently
            // overwrite the first request's closing figures instead of
            // being rejected as already-closed.
            if ($session->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => ['Only an open cash drawer session can be closed.'],
                ]);
            }

            $expectedClosing = $this->calculateExpectedClosing($session);
            $closingFloat = round((float) $data['closing_float'], 2);
            $variance = round($closingFloat - $expectedClosing, 2);

            $journalEntry = $variance !== 0.0 ? $this->postVarianceEntry($session, $variance) : null;

            $session->update([
                'closing_float' => $closingFloat,
                'expected_closing' => $expectedClosing,
                'variance' => $variance,
                'journal_entry_id' => $journalEntry?->id,
                'closed_at' => now(),
                'status' => 'closed',
            ]);

            return $session->fresh();
        });

        if ((float) $closed->variance !== 0.0) {
            $recipients = $this->recipientResolver->usersWithPermission(
                $closed->company_id,
                'users.view',
                $closed->branch_id,
            )->reject(fn ($user) => $user->id === Auth::id());
            Notification::send($recipients, new CashDrawerVarianceFlagged($closed));
        }

        return $closed;
    }

    /**
     * A shortage (negative variance) is a real loss of cash: debit the
     * expense account, credit Cash and Bank for the amount that's no longer
     * actually there. An overage (positive variance) is the reverse — extra
     * cash showed up, so Cash and Bank is debited and the gain is credited
     * to the same account. Without this, the ledger's cash balance reflects
     * only recorded sales/payments, never what was physically counted, so
     * the Trial Balance can stay "balanced" while the drawer is genuinely
     * short.
     */
    protected function postVarianceEntry(CashDrawerSession $session, float $variance): JournalEntry
    {
        $cashAccount = $this->resolveAccount($session->company_id, self::CASH_ACCOUNT_CODE);
        $shortOverAccount = $this->resolveAccount($session->company_id, self::CASH_SHORT_OVER_ACCOUNT_CODE);
        $amount = abs($variance);

        $lines = $variance < 0
            ? [
                ['account_id' => $shortOverAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Cash drawer shortage'],
                ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash drawer shortage'],
            ]
            : [
                ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Cash drawer overage'],
                ['account_id' => $shortOverAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash drawer overage'],
            ];

        // Always generated, never derived from anything the caller controls:
        // journal_entries.reference_number is unique per company, and this
        // runs inside the same transaction as the close itself so the
        // session id is guaranteed unique at this point, but a random
        // suffix is still added in case a session is ever somehow closed
        // more than once (e.g. after a manual data fix).
        $referenceNumber = 'CDVAR-'.$session->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        return $this->journalEntryService->postModuleEntry([
            'reference_number' => $referenceNumber,
            'entry_date' => now()->toDateString(),
            'description' => ($variance < 0 ? 'Cash drawer shortage' : 'Cash drawer overage')." on session #{$session->id}",
            'source_module' => 'pos_cash_drawer',
            'source_id' => $session->id,
            'lines' => $lines,
        ]);
    }

    /**
     * A shortage posted at close() is a real loss recorded in the books —
     * but if the cashier later hands over the missing cash, that needs its
     * own entry too, rather than silently editing the original one. This
     * reverses the shape of the shortage entry (debit Cash, credit Cash
     * Short/Over) for the amount actually recovered, and supports partial
     * recovery over multiple payments the same way customer/supplier
     * payments do.
     */
    public function recordVarianceRecovery(CashDrawerSession $session, array $data): CashDrawerVarianceRecovery
    {
        return DB::transaction(function () use ($session, $data) {
            $session = CashDrawerSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== 'closed' || $session->variance === null || (float) $session->variance >= 0) {
                throw ValidationException::withMessages([
                    'cash_drawer_session' => ['Only a closed session with a cash shortage can have a recovery recorded against it.'],
                ]);
            }

            $outstanding = round(abs((float) $session->variance) - (float) $session->recovered_amount, 2);
            $amount = round((float) $data['amount'], 2);

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => ["Recovery amount exceeds the outstanding shortage of {$outstanding}."],
                ]);
            }

            $cashAccount = $this->resolveAccount($session->company_id, self::CASH_ACCOUNT_CODE);
            $shortOverAccount = $this->resolveAccount($session->company_id, self::CASH_SHORT_OVER_ACCOUNT_CODE);

            $referenceNumber = 'CDREC-'.$session->id.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

            $journalEntry = $this->journalEntryService->postModuleEntry([
                'reference_number' => $referenceNumber,
                'entry_date' => now()->toDateString(),
                'description' => "Cash shortage recovery on session #{$session->id}",
                'source_module' => 'pos_cash_drawer_recovery',
                'source_id' => $session->id,
                'lines' => [
                    ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Cash shortage recovered'],
                    ['account_id' => $shortOverAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash shortage recovered'],
                ],
            ]);

            $recovery = CashDrawerVarianceRecovery::create([
                'cash_drawer_session_id' => $session->id,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
                'received_by' => Auth::id(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            $session->update(['recovered_amount' => (float) $session->recovered_amount + $amount]);

            return $recovery;
        });
    }

    protected function resolveAccount(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::query()->where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'chart_of_accounts' => ["Required account with code {$code} is missing from the chart of accounts."],
            ]);
        }

        return $account;
    }
}
