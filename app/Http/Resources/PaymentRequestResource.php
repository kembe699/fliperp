<?php

namespace App\Http\Resources;

use App\Models\PaymentRequest;
use App\Services\Finance\PaymentRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentRequest */
class PaymentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $pending = $this->relationLoaded('approvals')
            ? $this->approvals->where('round', $this->approval_round)->where('status', 'pending')->sortBy('sequence')->first()
            : null;

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'request_date' => $this->request_date?->toDateString(),
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'requester_name' => $this->requester?->name,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'payee_name' => $this->payee_name,
            'payee' => $this->payeeLabel(),
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,
            'base_amount' => (float) $this->base_amount,
            'expense_account_id' => $this->expense_account_id,
            'expense_account' => $this->expenseAccount ? "{$this->expenseAccount->code} {$this->expenseAccount->name}" : null,
            'grant_id' => $this->grant_id,
            'grant_code' => $this->grant?->code,
            'dea_id' => $this->dea_id,
            'dea_code' => $this->dea?->code,
            'project_id' => $this->project_id,
            'project_name' => $this->project?->name,
            'cost_centre_id' => $this->cost_centre_id,
            'cost_centre_code' => $this->costCentre?->code,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_reference' => $this->purchaseOrder?->reference_number,
            'rfq_id' => $this->rfq_id,
            'rfq_reference' => $this->rfq?->reference_number,
            'supplier_bill_id' => $this->supplier_bill_id,
            'supplier_bill_reference' => $this->supplierBill?->reference_number,
            'approval_round' => $this->approval_round,
            'current_step' => $pending ? ['sequence' => $pending->sequence, 'label' => $pending->label] : null,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'payment_date' => $this->payment_date?->toDateString(),
            'payment_type_id' => $this->payment_type_id,
            'payment_type_name' => $this->paymentType?->name,
            'payment_account' => $this->paymentAccount ? "{$this->paymentAccount->code} {$this->paymentAccount->name}" : null,
            'payment_reference' => $this->payment_reference,
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry_reference' => $this->journalEntry?->reference_number,
            'supplier_payment_id' => $this->supplier_payment_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_by_name' => $this->payer?->name,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'approvals' => $this->whenLoaded('approvals', fn () => $this->approvals->sortBy(['round', 'sequence'])->values()->map(fn ($a) => [
                'id' => $a->id,
                'round' => $a->round,
                'sequence' => $a->sequence,
                'label' => $a->label,
                'approver' => $a->user?->name ?? ($a->role ? "Any {$a->role->name}" : null),
                'status' => $a->status,
                'decided_by_name' => $a->decider?->name,
                'decided_at' => $a->decided_at?->toIso8601String(),
                'comment' => $a->comment,
            ])),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'id' => $e->id,
                'action' => $e->action,
                'from_status' => $e->from_status,
                'to_status' => $e->to_status,
                'comment' => $e->comment,
                'user_name' => $e->user?->name,
                'at' => $e->created_at?->toIso8601String(),
            ])),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($f) => [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'file_size' => $f->file_size,
                'mime_type' => $f->mime_type,
                'uploaded_by_name' => $f->uploader?->name,
                'uploaded_at' => $f->created_at?->toIso8601String(),
            ])),

            // What the current user may do now. Screens use it to show buttons;
            // every action is checked again on the server regardless.
            'can' => $user ? [
                'update' => in_array($this->status, PaymentRequest::EDITABLE, true) && $user->can('update', $this->resource),
                'delete' => $this->status === 'draft' && $user->can('delete', $this->resource),
                'submit' => in_array($this->status, PaymentRequest::EDITABLE, true) && $user->can('submit', $this->resource),
                'decide' => in_array($this->status, PaymentRequest::AWAITING_APPROVAL, true) && $user->can('approve', $this->resource) && app(PaymentRequestService::class)->canDecide($this->resource, $user),
                'pay' => $this->status === 'approved' && $user->can('pay', $this->resource),
                'cancel' => in_array($this->status, ['draft', 'submitted', 'partially_approved', 'rejected', 'approved'], true) && $user->can('cancel', $this->resource),
                'attach' => ! in_array($this->status, ['paid', 'cancelled'], true) && $user->can('update', $this->resource),
            ] : null,
        ];
    }
}
