<?php

namespace App\Services\Crm;

use App\Jobs\SendCrmEmailJob;
use App\Models\Company;
use App\Models\CrmEmail;
use App\Models\CrmMeeting;
use App\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CrmEmailService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmEmail::query()
            ->with(['lead', 'deal', 'customer', 'quotation', 'sentBy'])
            ->when($filters['lead_id'] ?? null, fn ($query, $id) => $query->where('lead_id', $id))
            ->when($filters['deal_id'] ?? null, fn ($query, $id) => $query->where('deal_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when($filters['sent_by'] ?? null, fn ($query, $id) => $query->where('sent_by', $id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function send(array $data): CrmEmail
    {
        $email = CrmEmail::create([
            'lead_id' => $data['lead_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'to_email' => $data['to_email'],
            'to_name' => $data['to_name'] ?? null,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'sent_by' => Auth::id(),
            'status' => 'queued',
        ]);

        SendCrmEmailJob::dispatch($email->id);

        return $email->fresh(['lead', 'deal', 'customer', 'sentBy']);
    }

    public function sendQuotationToContact(Quotation $quotation, array $overrides = []): CrmEmail
    {
        $quotation->loadMissing('customer');
        $customer = $quotation->customer;

        $toEmail = $overrides['to_email'] ?? $customer?->email;

        if (! $toEmail) {
            throw ValidationException::withMessages([
                'to_email' => ['This customer has no email address on file — provide one to send the quotation.'],
            ]);
        }

        $companyName = Company::find($quotation->company_id)?->name ?? config('app.name');

        $defaultBody = "Hi {$customer?->name},\n\n".
            "Please find attached quotation {$quotation->reference_number} from {$companyName}, ".
            'valid until '.$quotation->valid_until->format('M j, Y')." for a total of {$quotation->total_amount}.\n\n".
            "Let us know if you have any questions.\n\n{$companyName}";

        $email = CrmEmail::create([
            'lead_id' => $overrides['lead_id'] ?? null,
            'deal_id' => $overrides['deal_id'] ?? null,
            'customer_id' => $overrides['customer_id'] ?? $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'to_email' => $toEmail,
            'to_name' => $overrides['to_name'] ?? $customer?->name,
            'subject' => $overrides['subject'] ?? "Quotation {$quotation->reference_number} from {$companyName}",
            'body' => $overrides['body'] ?? $defaultBody,
            'sent_by' => Auth::id(),
            'status' => 'queued',
        ]);

        SendCrmEmailJob::dispatch($email->id);

        return $email->fresh(['lead', 'deal', 'customer', 'quotation', 'sentBy']);
    }

    /**
     * Fired automatically the moment a meeting is scheduled — no manual send
     * step. Resolves the external contact from the meeting's attendees and
     * skips silently if none has an email on file (an internal-only meeting).
     */
    public function sendMeetingConfirmation(CrmMeeting $meeting): ?CrmEmail
    {
        $meeting->loadMissing('attendees');
        $contact = $meeting->attendees->first(fn ($attendee) => (bool) $attendee->external_email);

        if (! $contact) {
            return null;
        }

        $email = CrmEmail::create([
            'lead_id' => $meeting->lead_id,
            'deal_id' => $meeting->deal_id,
            'customer_id' => $meeting->customer_id,
            'meeting_id' => $meeting->id,
            'to_email' => $contact->external_email,
            'to_name' => $contact->external_name,
            'subject' => "Meeting confirmed: {$meeting->title}",
            'body' => "Meeting \"{$meeting->title}\" scheduled for {$meeting->scheduled_at->format('M j, Y g:i A')} ({$meeting->duration_minutes} minutes).",
            'sent_by' => Auth::id() ?? $meeting->created_by,
            'status' => 'queued',
        ]);

        SendCrmEmailJob::dispatch($email->id);

        return $email->fresh(['lead', 'deal', 'customer', 'meeting', 'sentBy']);
    }
}
