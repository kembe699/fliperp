<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmMeeting;
use App\Models\CrmMeetingAttendee;
use App\Notifications\CrmMeetingScheduled;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    protected array $withRelations = ['lead', 'deal', 'customer', 'organizer', 'createdBy', 'attendees.user'];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmMeeting::query()
            ->with($this->withRelations)
            ->when($filters['lead_id'] ?? null, fn ($query, $id) => $query->where('lead_id', $id))
            ->when($filters['deal_id'] ?? null, fn ($query, $id) => $query->where('deal_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when($filters['organizer_id'] ?? null, fn ($query, $id) => $query->where('organizer_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('scheduled_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('scheduled_at', '<=', $to))
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }

    public function create(array $data): CrmMeeting
    {
        if (empty($data['lead_id']) && empty($data['deal_id']) && empty($data['customer_id'])) {
            throw ValidationException::withMessages([
                'lead_id' => ['A meeting must be linked to at least one of a lead, deal, or customer.'],
            ]);
        }

        return DB::transaction(function () use ($data) {
            $meeting = CrmMeeting::create([
                'lead_id' => $data['lead_id'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'location' => $data['location'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'organizer_id' => $data['organizer_id'] ?? Auth::id(),
                'status' => 'scheduled',
                'created_by' => Auth::id(),
            ]);

            $this->createAttendeeRows($meeting, $data['attendees'] ?? []);

            $meeting->load($this->withRelations);

            foreach ($meeting->attendees as $attendee) {
                $attendee->user?->notify(new CrmMeetingScheduled($meeting));
            }
            if (! $meeting->attendees->contains('user_id', $meeting->organizer_id)) {
                $meeting->organizer?->notify(new CrmMeetingScheduled($meeting));
            }

            return $meeting;
        });
    }

    public function updateStatus(CrmMeeting $meeting, string $status): CrmMeeting
    {
        $meeting->update(['status' => $status]);

        return $meeting->fresh($this->withRelations);
    }

    /**
     * Hand-rolled RFC 5545 iCalendar output — no calendar-provider OAuth is
     * needed since this is a static file staff import into whatever
     * calendar app they already use.
     */
    public function generateIcs(CrmMeeting $meeting): string
    {
        $meeting->loadMissing($this->withRelations);

        $start = $meeting->scheduled_at->clone()->utc();
        $end = $start->clone()->addMinutes($meeting->duration_minutes);
        $stamp = now()->utc();
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'erp.local';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('app.name').'//CRM Meetings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:crm-meeting-'.$meeting->id.'@'.$host,
            'DTSTAMP:'.$stamp->format('Ymd\THis\Z'),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$end->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escapeIcsText($meeting->title),
            'STATUS:'.($meeting->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
        ];

        if ($meeting->description) {
            $lines[] = 'DESCRIPTION:'.$this->escapeIcsText($meeting->description);
        }

        if ($meeting->meeting_link) {
            $lines[] = 'LOCATION:'.$this->escapeIcsText($meeting->meeting_link);
            $lines[] = 'URL:'.$meeting->meeting_link;
        } elseif ($meeting->location) {
            $lines[] = 'LOCATION:'.$this->escapeIcsText($meeting->location);
        }

        if ($meeting->organizer?->email) {
            $lines[] = 'ORGANIZER;CN='.$this->escapeIcsText($meeting->organizer->name).':MAILTO:'.$meeting->organizer->email;
        }

        foreach ($meeting->attendees as $attendee) {
            if ($attendee->user?->email) {
                $lines[] = 'ATTENDEE;CN='.$this->escapeIcsText($attendee->user->name).':MAILTO:'.$attendee->user->email;
            } elseif ($attendee->external_email) {
                $lines[] = 'ATTENDEE;CN='.$this->escapeIcsText($attendee->external_name ?? $attendee->external_email).':MAILTO:'.$attendee->external_email;
            }
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn ($line) => $this->foldIcsLine($line), $lines))."\r\n";
    }

    protected function escapeIcsText(string $value): string
    {
        return str_replace(
            ["\\", ',', ';', "\n"],
            ['\\\\', '\\,', '\\;', '\\n'],
            trim($value),
        );
    }

    /**
     * RFC 5545 requires lines no longer than 75 octets, continued with a
     * leading space on the next line — several calendar apps reject or
     * mis-render .ics files that skip this.
     */
    protected function foldIcsLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $remaining = $line;
        $first = true;

        while (strlen($remaining) > 0) {
            $limit = $first ? 75 : 74;
            $chunks[] = substr($remaining, 0, $limit);
            $remaining = substr($remaining, $limit);
            $first = false;
        }

        return implode("\r\n ", $chunks);
    }

    protected function createAttendeeRows(CrmMeeting $meeting, array $attendees): void
    {
        if (empty($attendees)) {
            $attendees[] = ['user_id' => $meeting->organizer_id];

            if ($contact = $this->resolveDefaultContact($meeting)) {
                $attendees[] = $contact;
            }
        }

        foreach ($attendees as $attendee) {
            CrmMeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'user_id' => $attendee['user_id'] ?? null,
                'external_name' => $attendee['external_name'] ?? null,
                'external_email' => $attendee['external_email'] ?? null,
            ]);
        }
    }

    protected function resolveDefaultContact(CrmMeeting $meeting): ?array
    {
        if ($meeting->lead_id && $lead = CrmLead::find($meeting->lead_id)) {
            return ['external_name' => $lead->name, 'external_email' => $lead->email];
        }

        if ($meeting->customer_id && $customer = Customer::find($meeting->customer_id)) {
            return ['external_name' => $customer->name, 'external_email' => $customer->email];
        }

        if ($meeting->deal_id && $deal = CrmDeal::with(['customer', 'lead'])->find($meeting->deal_id)) {
            if ($deal->customer) {
                return ['external_name' => $deal->customer->name, 'external_email' => $deal->customer->email];
            }
            if ($deal->lead) {
                return ['external_name' => $deal->lead->name, 'external_email' => $deal->lead->email];
            }
        }

        return null;
    }
}
