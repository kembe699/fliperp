<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\PaymentApprovalRule;
use App\Models\PaymentRequest;
use App\Models\PaymentRequestApproval;
use App\Models\PaymentRequestAttachment;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\SupplierBill;
use App\Models\User;
use App\Notifications\PaymentRequestApprovalNeeded;
use App\Notifications\PaymentRequestDecided;
use App\Notifications\PaymentRequestReadyToPay;
use App\Services\Grants\DimensionService;
use App\Services\Notifications\NotificationRecipientResolver;
use App\Services\Procurement\SupplierPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Payment requests: raised by staff, approved step by step according to the
 * company's amount rules, then paid by Finance.
 *
 * Allowed moves (anything else is refused here, whatever the caller):
 *   draft ─submit→ submitted ─approve→ partially_approved ─approve→ … → approved ─pay→ paid
 *   submitted | partially_approved ─reject→ rejected ─(edit)─submit→ submitted (new round)
 *   draft | submitted | partially_approved | rejected | approved ─cancel→ cancelled
 *
 * Every move writes a timeline event (who, when, comment) on top of the
 * generic audit log. The requester can never approve their own request, and
 * one person can approve only one step of a round, regardless of the
 * company's "allow self-approval" setting.
 */
class PaymentRequestService
{
    /** Where payments leave from until the "pay from account" choice exists (see resolvePaymentAccount). */
    public const CASH_ACCOUNT_CODE = '1000';

    public const DEFAULT_EXPENSE_ACCOUNT_CODE = '5200';

    /**
     * Private disk: quotes, invoices and receipts are only ever served through
     * the permission-checked download endpoint, never by a public URL.
     * (On the droplet storage/app, including private/, is a persistent volume.)
     */
    public const ATTACHMENT_DISK = 'local';

    public const LOADED = [
        'requester', 'supplier', 'branch', 'expenseAccount', 'grant', 'dea', 'project', 'costCentre',
        'purchaseOrder', 'rfq', 'supplierBill', 'paymentType', 'paymentAccount', 'journalEntry', 'supplierPayment', 'payer',
        'approvals.decider', 'approvals.role', 'approvals.user', 'events.user', 'attachments.uploader',
    ];

    public function __construct(
        protected ExchangeRateService $exchangeRateService,
        protected DimensionService $dimensionService,
        protected JournalEntryService $journalEntryService,
        protected SupplierPaymentService $supplierPaymentService,
        protected NotificationRecipientResolver $recipients,
    ) {}

    /**
     * @param  array{status?:string, requested_by?:int, from?:string, to?:string, project_id?:int, grant_id?:int, tab?:string, search?:string}  $filters
     */
    public function paginate(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return PaymentRequest::query()
            ->with(['requester', 'supplier', 'grant', 'project', 'approvals.role', 'approvals.user'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['requested_by'] ?? null, fn ($q, $id) => $q->where('requested_by', $id))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('request_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('request_date', '<=', $date))
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['grant_id'] ?? null, fn ($q, $id) => $q->where('grant_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('reference_number', 'ilike', "%{$term}%")
                ->orWhere('payee_name', 'ilike', "%{$term}%")
                ->orWhere('description', 'ilike', "%{$term}%")))
            ->when(($filters['tab'] ?? null) === 'mine', fn ($q) => $q->where('requested_by', $user->id))
            ->when(($filters['tab'] ?? null) === 'awaiting', fn ($q) => $this->scopeAwaiting($q, $user))
            ->latest('request_date')->latest('id')
            ->paginate($perPage);
    }

    public function create(array $data, User $user): PaymentRequest
    {
        $attributes = $this->attributes($data, $user->company_id);

        return DB::transaction(function () use ($attributes, $user) {
            $request = PaymentRequest::create($attributes + [
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'reference_number' => $this->nextReference($attributes['request_date']),
                'requested_by' => $user->id,
                'status' => 'draft',
            ]);
            $this->log($request, $user, 'created', null, 'draft');

            return $request->fresh(self::LOADED);
        });
    }

    public function update(PaymentRequest $request, array $data, User $user): PaymentRequest
    {
        $this->mustBe($request, PaymentRequest::EDITABLE, 'Only a draft or rejected payment request can be changed.');

        $merged = array_merge($request->only([
            'request_date', 'supplier_id', 'payee_name', 'description', 'amount', 'currency_code', 'exchange_rate',
            'expense_account_id', 'purchase_order_id', 'rfq_id', 'supplier_bill_id', ...DimensionService::KEYS,
        ]), $data);
        $merged['request_date'] = $merged['request_date'] instanceof \DateTimeInterface ? $merged['request_date']->format('Y-m-d') : $merged['request_date'];
        // A new date or currency picks up that date's rate unless one is typed.
        if ((isset($data['request_date']) || isset($data['currency_code'])) && ! array_key_exists('exchange_rate', $data)) {
            $merged['exchange_rate'] = null;
        }

        $request->update($this->attributes($merged, $request->company_id));
        $this->log($request, $user, 'edited', $request->status, $request->status);

        return $request->fresh(self::LOADED);
    }

    public function delete(PaymentRequest $request): void
    {
        $this->mustBe($request, ['draft'], 'Only a draft payment request can be deleted. Cancel it instead.');

        DB::transaction(function () use ($request) {
            foreach ($request->attachments as $attachment) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($attachment->file_path);
            }
            $request->delete();
        });
    }

    /**
     * Sends a draft (or a rejected request, after changes) for approval: picks
     * the rule covering its base amount, copies that rule's steps as a new
     * round, and tells whoever can approve the first step.
     */
    public function submit(PaymentRequest $request, User $user, ?string $comment = null): PaymentRequest
    {
        $this->mustBe($request, PaymentRequest::EDITABLE, 'Only a draft or rejected payment request can be submitted.');
        $this->assertBillCanTakeIt($request);

        $rule = $this->ruleFor($request);
        $this->assertStepsCanBeApproved($request, $rule);

        $request = DB::transaction(function () use ($request, $rule, $user, $comment) {
            $request = PaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->mustBe($request, PaymentRequest::EDITABLE, 'Only a draft or rejected payment request can be submitted.');

            $from = $request->status;
            $round = $request->approval_round + 1;
            foreach ($rule->steps as $step) {
                $request->approvals()->create([
                    'round' => $round,
                    'sequence' => $step->sequence,
                    'label' => $step->label,
                    'role_id' => $step->role_id,
                    'user_id' => $step->user_id,
                    'status' => 'pending',
                ]);
            }
            $request->update([
                'status' => 'submitted',
                'approval_round' => $round,
                'payment_approval_rule_id' => $rule->id,
                'submitted_at' => now(),
                'approved_at' => null,
            ]);
            $this->log($request, $user, $from === 'rejected' ? 'resubmitted' : 'submitted', $from, 'submitted', $comment);

            return $request;
        });

        $this->notifyStepApprovers($request);

        return $request->fresh(self::LOADED);
    }

    public function approve(PaymentRequest $request, User $user, ?string $comment = null): PaymentRequest
    {
        [$request, $finished] = DB::transaction(function () use ($request, $user, $comment) {
            $request = PaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
            $step = $this->stepFor($request, $user);

            $step->update(['status' => 'approved', 'decided_by' => $user->id, 'decided_at' => now(), 'comment' => $comment]);

            $from = $request->status;
            $finished = ! $request->approvals()->where('round', $request->approval_round)->where('status', 'pending')->exists();
            $to = $finished ? 'approved' : 'partially_approved';
            $request->update(['status' => $to, 'approved_at' => $finished ? now() : null]);
            $this->log($request, $user, 'approved', $from, $to, $comment, "Step {$step->sequence}: {$step->label}");

            return [$request, $finished];
        });

        if ($finished) {
            $this->notify(collect([$request->requester]), new PaymentRequestDecided($request, 'approved', $user, $comment));
            $this->notify($this->recipients->usersWithPermission($request->company_id, 'payment-requests.pay'), new PaymentRequestReadyToPay($request));
        } else {
            $this->notifyStepApprovers($request);
        }

        return $request->fresh(self::LOADED);
    }

    /** Sends it back to the requester, who can change it and submit it again. A reason is required. */
    public function reject(PaymentRequest $request, User $user, string $comment): PaymentRequest
    {
        $request = DB::transaction(function () use ($request, $user, $comment) {
            $request = PaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
            $step = $this->stepFor($request, $user);

            $step->update(['status' => 'rejected', 'decided_by' => $user->id, 'decided_at' => now(), 'comment' => $comment]);
            $request->approvals()->where('round', $request->approval_round)->where('status', 'pending')->update(['status' => 'cancelled']);

            $from = $request->status;
            $request->update(['status' => 'rejected', 'approved_at' => null]);
            $this->log($request, $user, 'rejected', $from, 'rejected', $comment, "Step {$step->sequence}: {$step->label}");

            return $request;
        });

        $this->notify(collect([$request->requester]), new PaymentRequestDecided($request, 'rejected', $user, $comment));

        return $request->fresh(self::LOADED);
    }

    /**
     * Records the payment of a fully approved request and posts it: through
     * the supplier-payment logic when it settles a supplier bill (clears the
     * payable, handles FX), otherwise as a journal Dr expense (with its coding)
     * / Cr the paying account.
     *
     * @param  array{payment_date:string, payment_type_id?:int|null, reference?:string|null, exchange_rate?:float|null}  $data
     */
    public function pay(PaymentRequest $request, User $user, array $data): PaymentRequest
    {
        $request = DB::transaction(function () use ($request, $user, $data) {
            $request = PaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->mustBe($request, ['approved'], 'Only a fully approved payment request can be paid.');
            if ($request->approvals()->where('round', $request->approval_round)->where('status', '!=', 'approved')->exists()) {
                throw ValidationException::withMessages(['status' => ['Every approval step must be approved before this request can be paid.']]);
            }

            $account = $this->resolvePaymentAccount($request);
            $paymentDate = $data['payment_date'];

            if ($request->supplier_bill_id) {
                $this->assertBillCanTakeIt($request);
                $payment = $this->supplierPaymentService->create([
                    'supplier_id' => $request->supplierBill->supplier_id,
                    'supplier_bill_id' => $request->supplier_bill_id,
                    'payment_date' => $paymentDate,
                    'amount' => (float) $request->amount,
                    'payment_type_id' => $data['payment_type_id'] ?? null,
                    'reference_number' => $data['reference'] ?? $request->reference_number,
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                ]);
                $links = ['supplier_payment_id' => $payment->id, 'journal_entry_id' => $payment->journal_entry_id];
            } else {
                $entry = $this->journalEntryService->postModuleEntry([
                    'branch_id' => $request->branch_id,
                    'reference_number' => $this->journalReference($request),
                    'entry_date' => $paymentDate,
                    'description' => "Payment request {$request->reference_number}: {$request->payeeLabel()}. {$request->description}",
                    'source_module' => 'payment_request',
                    'source_id' => $request->id,
                    'currency_code' => $request->currency_code,
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                    'lines' => [
                        ['account_id' => $this->expenseAccountId($request), 'debit' => (float) $request->amount, 'credit' => 0, 'description' => $request->description] + $request->only(DimensionService::KEYS),
                        ['account_id' => $account->id, 'debit' => 0, 'credit' => (float) $request->amount, 'description' => "Paid to {$request->payeeLabel()}"],
                    ],
                ]);
                $links = ['journal_entry_id' => $entry->id];
            }

            $request->update($links + [
                'status' => 'paid',
                'payment_date' => $paymentDate,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'payment_account_id' => $account->id,
                'payment_reference' => $data['reference'] ?? null,
                'paid_at' => now(),
                'paid_by' => $user->id,
            ]);
            $this->log($request, $user, 'paid', 'approved', 'paid', $data['reference'] ?? null);

            return $request;
        });

        $this->notify(collect([$request->requester]), new PaymentRequestDecided($request, 'paid', $user, null));

        return $request->fresh(self::LOADED);
    }

    public function cancel(PaymentRequest $request, User $user, ?string $comment = null): PaymentRequest
    {
        return DB::transaction(function () use ($request, $user, $comment) {
            $request = PaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->mustBe($request, ['draft', 'submitted', 'partially_approved', 'rejected', 'approved'], 'A paid or cancelled payment request cannot be cancelled.');

            $from = $request->status;
            $request->approvals()->where('round', $request->approval_round)->where('status', 'pending')->update(['status' => 'cancelled']);
            $request->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->log($request, $user, 'cancelled', $from, 'cancelled', $comment);

            return $request->fresh(self::LOADED);
        });
    }

    /** @param  UploadedFile[]  $files */
    public function addAttachments(PaymentRequest $request, array $files, User $user): PaymentRequest
    {
        if (in_array($request->status, ['paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['attachments' => ['Documents can no longer be added to a paid or cancelled request.']]);
        }

        foreach ($files as $file) {
            $request->attachments()->create([
                'file_path' => $file->store("payment-requests/{$request->company_id}", self::ATTACHMENT_DISK),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'uploaded_by' => $user->id,
            ]);
            $this->log($request, $user, 'attachment_added', $request->status, $request->status, $file->getClientOriginalName());
        }

        return $request->fresh(self::LOADED);
    }

    public function deleteAttachment(PaymentRequest $request, PaymentRequestAttachment $attachment, User $user): PaymentRequest
    {
        abort_unless($attachment->payment_request_id === $request->id, 404);
        $this->mustBe($request, PaymentRequest::EDITABLE, 'Documents can only be removed while the request is a draft or rejected.');

        Storage::disk(self::ATTACHMENT_DISK)->delete($attachment->file_path);
        $attachment->delete();
        $this->log($request, $user, 'attachment_removed', $request->status, $request->status, $attachment->file_name);

        return $request->fresh(self::LOADED);
    }

    /** Printable voucher with the approval trail, for the audit file. */
    public function pdf(PaymentRequest $request): DomPdf
    {
        $request->loadMissing(self::LOADED);
        $company = Company::find($request->company_id);

        return Pdf::loadView('pdf.payment-request', [
            'request' => $request,
            'company' => $company,
            'branch' => $request->branch,
            'baseCurrency' => $company?->currency_code ?? 'USD',
        ])->setPaper('a4', 'portrait');
    }

    /** True when $user may decide the step waiting now (used for the screen's buttons; the actions re-check). */
    public function canDecide(PaymentRequest $request, User $user): bool
    {
        try {
            $this->stepFor($request, $user);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /** Active users who could approve $approval for $request (never the requester). */
    public function candidatesFor(PaymentRequest $request, PaymentRequestApproval|\App\Models\PaymentApprovalRuleStep $step): Collection
    {
        return User::query()
            ->with('roles')
            ->where('company_id', $request->company_id)
            ->where('is_active', true)
            ->where('id', '!=', $request->requested_by)
            ->when($step->user_id, fn ($q) => $q->whereKey($step->user_id))
            ->get()
            ->filter(fn (User $u) => ($step->user_id ? true : $u->roles->contains('id', $step->role_id)) && $u->can('payment-requests.approve'))
            ->values();
    }

    // ── Rules and steps ─────────────────────────────────────────────────────

    protected function ruleFor(PaymentRequest $request): PaymentApprovalRule
    {
        $amount = (float) $request->base_amount;
        $rule = PaymentApprovalRule::query()
            ->where('company_id', $request->company_id)
            ->where('is_active', true)
            ->with('steps')
            ->get()
            ->first(fn (PaymentApprovalRule $r) => $r->covers($amount));

        if (! $rule || $rule->steps->isEmpty()) {
            $base = Company::find($request->company_id)?->currency_code ?? 'USD';
            throw ValidationException::withMessages(['amount' => [
                'No approval rule covers '.$base.' '.number_format($amount, 2).'. Ask an administrator to set up payment approval rules (Settings › Payment Approvals).',
            ]]);
        }

        return $rule;
    }

    /** Refuses to submit a request that could never be fully approved (no eligible approver, or too few different people). */
    protected function assertStepsCanBeApproved(PaymentRequest $request, PaymentApprovalRule $rule): void
    {
        $everyone = collect();
        foreach ($rule->steps as $step) {
            $candidates = $this->candidatesFor($request, $step);
            if ($candidates->isEmpty()) {
                throw ValidationException::withMessages(['approval' => [
                    "Nobody other than the requester can approve the step \"{$step->label}\". Ask an administrator to assign that role or change the approval rule.",
                ]]);
            }
            $everyone = $everyone->merge($candidates->pluck('id'));
        }
        if ($everyone->unique()->count() < $rule->steps->count()) {
            throw ValidationException::withMessages(['approval' => [
                "This amount needs {$rule->steps->count()} different approvers, but fewer people than that can approve it.",
            ]]);
        }
    }

    /** The pending step $user may decide now, or a clear reason why not. */
    protected function stepFor(PaymentRequest $request, User $user): PaymentRequestApproval
    {
        $deny = fn (string $message) => throw ValidationException::withMessages(['approval' => [$message]]);

        if (! in_array($request->status, PaymentRequest::AWAITING_APPROVAL, true)) {
            $deny('This payment request is not waiting for approval.');
        }
        if ($user->id === $request->requested_by) {
            $deny('You raised this payment request, so someone else must approve it.');
        }
        if (! $user->can('payment-requests.approve')) {
            $deny('You do not have permission to approve payment requests.');
        }

        $step = $request->pendingApproval();
        if (! $step) {
            $deny('There is no approval step waiting on this request.');
        }
        $user->loadMissing('roles');
        if (! $step->isFor($user)) {
            $deny("This step is for {$step->label}.");
        }
        $alreadyApproved = $request->approvals()
            ->where('round', $request->approval_round)
            ->where('decided_by', $user->id)
            ->where('status', 'approved')
            ->exists();
        if ($alreadyApproved) {
            $deny('You already approved an earlier step of this request; a different approver must approve this one.');
        }

        return $step;
    }

    /** Requests waiting on a step $user could decide now. */
    protected function scopeAwaiting($query, User $user)
    {
        $roleIds = $user->roles()->pluck('roles.id')->all();

        return $query
            ->whereIn('status', PaymentRequest::AWAITING_APPROVAL)
            ->where('requested_by', '!=', $user->id)
            ->whereHas('approvals', function ($q) use ($user, $roleIds) {
                $q->whereColumn('payment_request_approvals.round', 'payment_requests.approval_round')
                    ->where('status', 'pending')
                    ->whereRaw('sequence = (select min(a2.sequence) from payment_request_approvals a2 where a2.payment_request_id = payment_request_approvals.payment_request_id and a2.round = payment_request_approvals.round and a2.status = ?)', ['pending'])
                    ->where(fn ($w) => $w->where('user_id', $user->id)->orWhere(fn ($r) => $r->whereNull('user_id')->whereIn('role_id', $roleIds ?: [0])));
            });
    }

    // ── Validation helpers ──────────────────────────────────────────────────

    /** Normalises and checks the request's own fields (currency, coding, links); returns model attributes. */
    protected function attributes(array $data, int $companyId): array
    {
        $date = $data['request_date'] ?? now()->toDateString();

        $bill = ! empty($data['supplier_bill_id']) ? SupplierBill::withoutGlobalScopes()->where('company_id', $companyId)->find($data['supplier_bill_id']) : null;
        $po = ! empty($data['purchase_order_id']) ? PurchaseOrder::withoutGlobalScopes()->where('company_id', $companyId)->find($data['purchase_order_id']) : null;
        $rfq = ! empty($data['rfq_id']) ? Rfq::withoutGlobalScopes()->where('company_id', $companyId)->find($data['rfq_id']) : null;
        foreach (['supplier_bill_id' => $bill, 'purchase_order_id' => $po, 'rfq_id' => $rfq] as $key => $found) {
            if (! empty($data[$key]) && ! $found) {
                throw ValidationException::withMessages([$key => ['The selected document does not exist.']]);
            }
        }

        // A bill or order already names the supplier; the request follows it.
        $supplierId = $data['supplier_id'] ?? null;
        $linkedSupplier = $bill?->supplier_id ?? $po?->supplier_id;
        if ($linkedSupplier && $supplierId && (int) $supplierId !== (int) $linkedSupplier) {
            throw ValidationException::withMessages(['supplier_id' => ['The supplier must match the linked bill or purchase order.']]);
        }
        $supplierId = $supplierId ?: $linkedSupplier;
        if (! $supplierId && blank($data['payee_name'] ?? null)) {
            throw ValidationException::withMessages(['payee_name' => ['Choose a supplier or type the payee name.']]);
        }

        $currencyCode = $data['currency_code'] ?? null;
        if ($bill) {
            $currencyCode = $currencyCode ?: $bill->currency_code;
            if (strtoupper($currencyCode) !== strtoupper($bill->currency_code)) {
                throw ValidationException::withMessages(['currency_code' => ["The linked bill is in {$bill->currency_code}; the request must be too."]]);
            }
        }
        [$currency, $rate] = $this->exchangeRateService->resolve($companyId, $currencyCode, $date, $data['exchange_rate'] ?? null);

        $this->dimensionService->validate($companyId, [$data], $date, '');

        $amount = round((float) $data['amount'], 2);

        return [
            'request_date' => $date,
            'supplier_id' => $supplierId,
            'payee_name' => $supplierId ? null : trim((string) $data['payee_name']),
            'description' => $data['description'],
            'amount' => $amount,
            'currency_code' => $currency,
            'exchange_rate' => $rate,
            'base_amount' => round($amount / $rate, 2),
            'expense_account_id' => $data['expense_account_id'] ?? null,
            'purchase_order_id' => $po?->id,
            'rfq_id' => $rfq?->id,
            'supplier_bill_id' => $bill?->id,
        ] + DimensionService::of($data);
    }

    /** A request settling a bill can't pay more than the bill still owes. */
    protected function assertBillCanTakeIt(PaymentRequest $request): void
    {
        if (! $request->supplier_bill_id) {
            return;
        }
        $bill = SupplierBill::withoutGlobalScopes()->findOrFail($request->supplier_bill_id);
        $due = round((float) $bill->total_amount - (float) $bill->amount_paid, 2);
        if ((float) $request->amount > $due) {
            throw ValidationException::withMessages(['amount' => ["Bill {$bill->reference_number} only has {$bill->currency_code} ".number_format($due, 2).' left to pay.']]);
        }
    }

    protected function mustBe(PaymentRequest $request, array $statuses, string $message): void
    {
        if (! in_array($request->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => [$message]]);
        }
    }

    // ── Posting helpers ─────────────────────────────────────────────────────

    /**
     * The account the money leaves from. Today every payment leaves from the
     * single cash/bank account (1000), like supplier and payroll payments.
     * When several bank/cash accounts are supported (G1), read the request's
     * chosen account here; payment_account_id already records it.
     */
    protected function resolvePaymentAccount(PaymentRequest $request): ChartOfAccount
    {
        return $this->account($request->company_id, self::CASH_ACCOUNT_CODE);
    }

    protected function expenseAccountId(PaymentRequest $request): int
    {
        return $request->expense_account_id ?: $this->account($request->company_id, self::DEFAULT_EXPENSE_ACCOUNT_CODE)->id;
    }

    protected function account(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();
        if (! $account) {
            throw ValidationException::withMessages(['chart_of_accounts' => ["Required account with code {$code} is missing from the chart of accounts."]]);
        }

        return $account;
    }

    protected function journalReference(PaymentRequest $request): string
    {
        $base = "{$request->reference_number}-PAY";
        $ref = $base;
        $n = 2;
        while (\App\Models\JournalEntry::withoutGlobalScopes()->where('company_id', $request->company_id)->where('reference_number', $ref)->exists()) {
            $ref = "{$base}-".$n++;
        }

        return $ref;
    }

    /** PR-2026-0001 … numbered per company and per year of the request date. */
    protected function nextReference(string $date): string
    {
        $prefix = 'PR-'.substr($date, 0, 4).'-';
        $n = PaymentRequest::query()->where('reference_number', 'like', $prefix.'%')->count() + 1;
        do {
            $ref = $prefix.str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
        } while (PaymentRequest::query()->where('reference_number', $ref)->exists());

        return $ref;
    }

    // ── Timeline and notifications ──────────────────────────────────────────

    protected function log(PaymentRequest $request, User $user, string $action, ?string $from, ?string $to, ?string $comment = null, ?string $step = null): void
    {
        $request->events()->create([
            'user_id' => $user->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            // e.g. "Step 1: Program Officer – Within budget", or just the step when no comment was given.
            'comment' => implode(' – ', array_filter([$step, filled($comment) ? trim($comment) : null])) ?: null,
        ]);
    }

    protected function notifyStepApprovers(PaymentRequest $request): void
    {
        $step = $request->pendingApproval();
        if ($step) {
            $this->notify($this->candidatesFor($request, $step), new PaymentRequestApprovalNeeded($request, $step));
        }
    }

    /**
     * In-app (and email) notification. A failure to notify never undoes the
     * approval or payment it reports: it is logged and the action stands.
     */
    protected function notify(Collection $users, BaseNotification $notification): void
    {
        $users = $users->filter()->unique('id');
        if ($users->isEmpty()) {
            return;
        }
        try {
            Notification::send($users, $notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
