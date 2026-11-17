<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request to pay someone, approved in steps according to the company's
 * amount rules, then paid by Finance. See PaymentRequestService for the
 * allowed status moves.
 */
class PaymentRequest extends TenantModel
{
    use Auditable;

    public const STATUSES = ['draft', 'submitted', 'partially_approved', 'approved', 'paid', 'rejected', 'cancelled'];

    /** Statuses in which the requester can still change the request. */
    public const EDITABLE = ['draft', 'rejected'];

    /** Statuses in which an approver can act. */
    public const AWAITING_APPROVAL = ['submitted', 'partially_approved'];

    protected $fillable = [
        'company_id', 'branch_id', 'reference_number', 'request_date', 'requested_by',
        'supplier_id', 'payee_name', 'description',
        'amount', 'currency_code', 'exchange_rate', 'base_amount',
        'expense_account_id', 'grant_id', 'dea_id', 'project_id', 'cost_centre_id',
        'purchase_order_id', 'rfq_id', 'supplier_bill_id',
        'status', 'approval_round', 'payment_approval_rule_id', 'submitted_at', 'approved_at',
        'payment_date', 'payment_type_id', 'payment_account_id', 'payment_reference',
        'journal_entry_id', 'supplier_payment_id', 'paid_at', 'paid_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:10',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** The payee as it should read on screen and on the voucher. */
    public function payeeLabel(): string
    {
        return $this->supplier?->name ?? (string) $this->payee_name;
    }

    /** Steps of the current approval round, in order. */
    public function currentApprovals(): HasMany
    {
        return $this->approvals()->where('round', $this->approval_round)->orderBy('sequence');
    }

    /** The step waiting for a decision now, if any. */
    public function pendingApproval(): ?PaymentRequestApproval
    {
        return $this->approvals()
            ->where('round', $this->approval_round)
            ->where('status', 'pending')
            ->orderBy('sequence')
            ->first();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PaymentRequestApproval::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentRequestEvent::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PaymentRequestAttachment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }

    public function dea(): BelongsTo
    {
        return $this->belongsTo(GrantDea::class, 'dea_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MeProject::class, 'project_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplierBill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PaymentApprovalRule::class, 'payment_approval_rule_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
