<?php

namespace App\Services\Crm;

use App\Models\CrmBookingPage;
use App\Models\CrmMeeting;
use App\Models\CrmMeetingAttendee;
use App\Notifications\CrmMeetingScheduled;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Public meeting booking: which slots a booking page offers on a day, and
 * booking one (creates/links the CRM contact, the meeting, notifies the host
 * and emails the client a confirmation with a calendar invite).
 */
class BookingService
{
    public function __construct(
        protected MarketingContactService $contacts,
        protected CrmEmailService $emails,
    ) {}

    /**
     * Free start times on $date (Y-m-d, in the page's timezone), as UTC ISO strings.
     *
     * @return array<int, string>
     */
    public function availableSlots(CrmBookingPage $page, string $date): array
    {
        $tz = $page->timezone ?: 'UTC';
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        $now = CarbonImmutable::now($tz);

        if (! in_array($day->dayOfWeekIso, array_map('intval', $page->days ?? []), true)) {
            return [];
        }
        if ($day->gt($now->startOfDay()->addDays($page->max_days_ahead)) || $day->lt($now->startOfDay())) {
            return [];
        }

        $earliest = $now->addHours($page->min_notice_hours);
        [$sh, $sm] = array_map('intval', explode(':', $page->start_time));
        [$eh, $em] = array_map('intval', explode(':', $page->end_time));
        $open = $day->setTime($sh, $sm);
        $close = $day->setTime($eh, $em);
        $length = $page->duration_minutes;

        $busy = $this->busyRanges($page, $open->subDay(), $close->addDay());

        $slots = [];
        for ($start = $open; $start->addMinutes($length)->lte($close); $start = $start->addMinutes($length)) {
            if ($start->lt($earliest)) {
                continue;
            }
            $end = $start->addMinutes($length);
            if (! $this->overlapsBusy($start, $end, $busy, $page->buffer_minutes)) {
                $slots[] = $start->utc()->toIso8601String();
            }
        }

        return $slots;
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, company_name?: ?string, notes?: ?string, start: string}  $data
     */
    public function book(CrmBookingPage $page, array $data): CrmMeeting
    {
        $start = CarbonImmutable::parse($data['start'])->utc();
        $localDate = $start->setTimezone($page->timezone ?: 'UTC')->format('Y-m-d');

        $meeting = DB::transaction(function () use ($page, $data, $start, $localDate) {
            // Serialises bookings on this page so two people can't take the same slot.
            CrmBookingPage::query()->whereKey($page->id)->lockForUpdate()->first();

            if (! in_array($start->toIso8601String(), $this->availableSlots($page, $localDate), true)) {
                throw ValidationException::withMessages([
                    'start' => ['That time is no longer available. Please pick another slot.'],
                ]);
            }

            $contact = $this->contacts->resolve($page->company_id, $data, "Booking: {$page->title}");

            $meeting = CrmMeeting::create([
                'company_id' => $page->company_id,
                'lead_id' => $contact['lead']?->id,
                'customer_id' => $contact['customer']?->id,
                'booking_page_id' => $page->id,
                'title' => "{$page->title} with ".trim($data['name']),
                'description' => $page->description,
                'booking_notes' => $data['notes'] ?? null,
                'scheduled_at' => $start,
                'duration_minutes' => $page->duration_minutes,
                'location' => $page->location,
                'meeting_link' => $page->meeting_link,
                'organizer_id' => $page->host_user_id,
                'status' => 'scheduled',
                'created_by' => $page->host_user_id,
            ]);

            CrmMeetingAttendee::create(['meeting_id' => $meeting->id, 'user_id' => $page->host_user_id]);
            CrmMeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'external_name' => trim($data['name']),
                'external_email' => trim($data['email']),
            ]);

            return $meeting->load(['lead', 'customer', 'organizer', 'attendees.user']);
        });

        $meeting->organizer?->notify(new CrmMeetingScheduled($meeting));
        $this->emails->sendMeetingConfirmation($meeting);

        return $meeting;
    }

    /**
     * The host's scheduled meetings overlapping the window, as [start, end] pairs.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function busyRanges(CrmBookingPage $page, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CrmMeeting::query()
            ->where('company_id', $page->company_id)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$from->utc(), $to->utc()])
            ->where(function ($query) use ($page) {
                $query->where('organizer_id', $page->host_user_id)
                    ->orWhereHas('attendees', fn ($q) => $q->where('user_id', $page->host_user_id));
            })
            ->get(['scheduled_at', 'duration_minutes'])
            ->map(function (CrmMeeting $meeting) {
                $start = CarbonImmutable::parse($meeting->scheduled_at);

                return [$start, $start->addMinutes($meeting->duration_minutes)];
            })
            ->all();
    }

    protected function overlapsBusy(CarbonImmutable $start, CarbonImmutable $end, array $busy, int $buffer): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start->lt($busyEnd->addMinutes($buffer)) && $end->gt($busyStart->subMinutes($buffer))) {
                return true;
            }
        }

        return false;
    }
}
