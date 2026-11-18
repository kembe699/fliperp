<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CrmBookingPage;
use App\Models\CrmLeadMagnet;
use App\Models\CrmLeadMagnetSubmission;
use App\Services\Crm\BookingService;
use App\Services\Crm\EmailCampaignService;
use App\Services\Crm\LeadMagnetService;
use App\Support\Modules;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unauthenticated CRM endpoints: public booking pages, lead magnet pages and
 * downloads, and campaign email tracking (open pixel, click redirect,
 * unsubscribe). With no signed-in user no CompanyScope applies, so each
 * lookup is by an unguessable slug/token and checks the owning company is
 * active and has the CRM module.
 */
class PublicCrmController extends Controller
{
    // --- Booking pages ---

    public function bookingPage(string $slug): JsonResponse
    {
        $page = $this->findBookingPage($slug);
        $company = Company::find($page->company_id);

        return $this->success([
            'slug' => $page->slug,
            'title' => $page->title,
            'description' => $page->description,
            'duration_minutes' => $page->duration_minutes,
            'timezone' => $page->timezone,
            'days' => $page->days,
            'max_days_ahead' => $page->max_days_ahead,
            'location' => $page->location,
            'has_meeting_link' => (bool) $page->meeting_link,
            'host_name' => $page->host?->name,
            'company' => ['name' => $company?->name, 'logo_url' => $company?->logo_url],
        ]);
    }

    public function bookingSlots(Request $request, string $slug, BookingService $bookings): JsonResponse
    {
        $page = $this->findBookingPage($slug);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return $this->success(['date' => $data['date'], 'slots' => $bookings->availableSlots($page, $data['date'])]);
    }

    /** Which of the next days (in the page's timezone) still have at least one free slot. */
    public function bookingDays(string $slug, BookingService $bookings): JsonResponse
    {
        $page = $this->findBookingPage($slug);
        $today = CarbonImmutable::now($page->timezone ?: 'UTC')->startOfDay();
        $days = [];
        for ($i = 0; $i <= min($page->max_days_ahead, 60); $i++) {
            $date = $today->addDays($i)->format('Y-m-d');
            if ($bookings->availableSlots($page, $date) !== []) {
                $days[] = $date;
            }
        }

        return $this->success(['days' => $days]);
    }

    public function book(Request $request, string $slug, BookingService $bookings): JsonResponse
    {
        $page = $this->findBookingPage($slug);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'start' => ['required', 'date'],
        ]);

        $meeting = $bookings->book($page, $data);

        return $this->success([
            'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
            'duration_minutes' => $meeting->duration_minutes,
            'timezone' => $page->timezone,
            'location' => $page->location,
            'meeting_link' => $page->meeting_link,
        ], 'Your meeting is booked. A confirmation is on its way to your inbox.', 201);
    }

    // --- Lead magnets ---

    public function leadMagnet(string $slug): JsonResponse
    {
        $magnet = $this->findLeadMagnet($slug);
        DB::table('crm_lead_magnets')->where('id', $magnet->id)->increment('views_count');
        $company = Company::find($magnet->company_id);

        return $this->success([
            'slug' => $magnet->slug,
            'title' => $magnet->title,
            'headline' => $magnet->headline,
            'description' => $magnet->description,
            'button_label' => $magnet->button_label ?: 'Get it now',
            // Shown on the cover art, e.g. "PDF"; "LINK" when the download is a web link.
            'file_type' => $magnet->file_name ? strtoupper(pathinfo($magnet->file_name, PATHINFO_EXTENSION)) ?: 'FILE' : 'LINK',
            'ask_phone' => $magnet->ask_phone,
            'ask_company' => $magnet->ask_company,
            'company' => ['name' => $company?->name, 'logo_url' => $company?->logo_url],
        ]);
    }

    public function submitLeadMagnet(Request $request, string $slug, LeadMagnetService $magnets): JsonResponse
    {
        $magnet = $this->findLeadMagnet($slug);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => [$magnet->ask_phone ? 'required' : 'nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:150'],
        ]);

        $submission = $magnets->submit($magnet, $data);

        return $this->success([
            'thank_you_message' => $magnet->thank_you_message ?: "Thanks! We've also emailed you a link.",
            'download_url' => $magnets->downloadUrl($submission),
        ], 'Thanks for signing up.', 201);
    }

    public function download(string $token): StreamedResponse|RedirectResponse|JsonResponse
    {
        $submission = CrmLeadMagnetSubmission::query()->where('download_token', $token)->first();
        $magnet = $submission ? CrmLeadMagnet::query()->find($submission->lead_magnet_id) : null;
        if (! $submission || ! $magnet) {
            return $this->error('This download link is not valid.', null, 404);
        }

        $submission->forceFill([
            'download_count' => $submission->download_count + 1,
            'downloaded_at' => $submission->downloaded_at ?? now(),
        ])->save();

        if ($magnet->file_path && Storage::disk(LeadMagnetService::DISK)->exists($magnet->file_path)) {
            return Storage::disk(LeadMagnetService::DISK)->download($magnet->file_path, $magnet->file_name ?: basename($magnet->file_path));
        }
        if ($magnet->external_url) {
            return redirect()->away($magnet->external_url);
        }

        return $this->error('This file is no longer available.', null, 404);
    }

    // --- Campaign email tracking ---

    public function trackOpen(string $token, EmailCampaignService $campaigns): Response
    {
        $campaigns->recordOpen($token);

        // 1×1 transparent GIF.
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function trackClick(Request $request, string $token, EmailCampaignService $campaigns): RedirectResponse|JsonResponse
    {
        $target = $campaigns->recordClick($token, (string) $request->query('u', ''), (string) $request->query('s', ''));

        return $target ? redirect()->away($target) : $this->error('This link is not valid.', null, 404);
    }

    public function unsubscribe(string $token, EmailCampaignService $campaigns): JsonResponse
    {
        $result = $campaigns->unsubscribe($token);

        return $result
            ? $this->success($result, "You've been unsubscribed.")
            : $this->error('This unsubscribe link is not valid.', null, 404);
    }

    // --- Lookups ---

    protected function findBookingPage(string $slug): CrmBookingPage
    {
        $page = CrmBookingPage::query()->with('host:id,name')->where('slug', $slug)->where('is_active', true)->first();
        abort_unless($page && $this->companyAcceptsPublic($page->company_id), 404, 'This booking page is not available.');

        return $page;
    }

    protected function findLeadMagnet(string $slug): CrmLeadMagnet
    {
        $magnet = CrmLeadMagnet::query()->where('slug', $slug)->where('is_active', true)->first();
        abort_unless($magnet && $this->companyAcceptsPublic($magnet->company_id), 404, 'This page is not available.');

        return $magnet;
    }

    protected function companyAcceptsPublic(int $companyId): bool
    {
        $company = Company::find($companyId);

        return $company
            && $company->is_active
            && $company->status === 'active'
            && in_array('crm', Modules::enabledFor($company->enabled_modules), true);
    }
}
