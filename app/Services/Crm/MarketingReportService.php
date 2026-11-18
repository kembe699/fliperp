<?php

namespace App\Services\Crm;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * CRM marketing analytics for one company over a date range: direct emails,
 * campaign performance, lead magnets, bookings, and where leads come from.
 * Uses DB::table (no model scope), so every query names the company.
 */
class MarketingReportService
{
    public function summary(int $companyId, string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        $range = [$start, $end];

        $emails = DB::table('crm_emails')->where('company_id', $companyId)->whereBetween('created_at', $range)
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'sent') AS sent, COUNT(*) FILTER (WHERE status = 'failed') AS failed, COUNT(*) FILTER (WHERE status = 'queued') AS queued")
            ->first();

        $campaignTotals = DB::table('crm_email_campaign_recipients')->where('company_id', $companyId)->whereBetween('sent_at', $range)
            ->selectRaw("COUNT(*) AS sent, COUNT(opened_at) AS opened, COUNT(clicked_at) AS clicked, COUNT(unsubscribed_at) AS unsubscribed, COUNT(DISTINCT campaign_id) AS campaigns")
            ->first();
        $campaignSent = (int) $campaignTotals->sent;

        $campaigns = DB::table('crm_email_campaigns as c')
            ->leftJoin('crm_email_campaign_recipients as r', 'r.campaign_id', '=', 'c.id')
            ->where('c.company_id', $companyId)
            ->whereIn('c.status', ['sending', 'sent', 'cancelled'])
            ->where(fn ($q) => $q->whereBetween('c.started_at', $range)->orWhereBetween('c.finished_at', $range))
            ->groupBy('c.id', 'c.name', 'c.status', 'c.started_at')
            ->orderByDesc('c.started_at')
            ->limit(10)
            ->get([
                'c.id', 'c.name', 'c.status', 'c.started_at',
                DB::raw("COUNT(r.id) FILTER (WHERE r.status = 'sent') AS sent"),
                DB::raw('COUNT(r.opened_at) AS opened'),
                DB::raw('COUNT(r.clicked_at) AS clicked'),
                DB::raw('COUNT(r.unsubscribed_at) AS unsubscribed'),
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'status' => $row->status,
                'started_at' => $row->started_at,
                'sent' => (int) $row->sent,
                'open_rate' => $this->rate($row->opened, $row->sent),
                'click_rate' => $this->rate($row->clicked, $row->sent),
                'unsubscribed' => (int) $row->unsubscribed,
            ]);

        $magnets = DB::table('crm_lead_magnets as m')
            ->where('m.company_id', $companyId)
            ->orderByDesc('m.created_at')
            ->get(['m.id', 'm.title', 'm.views_count', 'm.is_active'])
            ->map(function ($magnet) use ($range) {
                $subs = DB::table('crm_lead_magnet_submissions')->where('lead_magnet_id', $magnet->id);
                $total = (clone $subs)->count();

                return [
                    'id' => $magnet->id,
                    'title' => $magnet->title,
                    'is_active' => (bool) $magnet->is_active,
                    'views' => (int) $magnet->views_count,
                    'submissions' => $total,
                    'submissions_in_range' => (clone $subs)->whereBetween('created_at', $range)->count(),
                    'downloads' => (clone $subs)->whereNotNull('downloaded_at')->count(),
                    'conversion_rate' => $this->rate($total, $magnet->views_count),
                ];
            });

        $bookings = DB::table('crm_meetings')->where('company_id', $companyId)->whereNotNull('booking_page_id');
        $bookingPages = DB::table('crm_booking_pages as p')
            ->leftJoin('crm_meetings as m', fn ($join) => $join->on('m.booking_page_id', '=', 'p.id')->whereBetween('m.created_at', $range))
            ->where('p.company_id', $companyId)
            ->groupBy('p.id', 'p.title')
            ->orderBy('p.title')
            ->get(['p.id', 'p.title', DB::raw('COUNT(m.id) AS bookings')])
            ->map(fn ($row) => ['id' => $row->id, 'title' => $row->title, 'bookings' => (int) $row->bookings]);

        $leads = DB::table('crm_leads')->where('company_id', $companyId)->whereNull('deleted_at')->whereBetween('created_at', $range);
        $sources = (clone $leads)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'Unknown') AS source, COUNT(*) AS total")
            ->groupByRaw("COALESCE(NULLIF(source, ''), 'Unknown')")
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['source' => $row->source, 'total' => (int) $row->total]);

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'emails' => ['sent' => (int) $emails->sent, 'failed' => (int) $emails->failed, 'queued' => (int) $emails->queued],
            'campaigns' => [
                'campaigns' => (int) $campaignTotals->campaigns,
                'sent' => $campaignSent,
                'opened' => (int) $campaignTotals->opened,
                'clicked' => (int) $campaignTotals->clicked,
                'unsubscribed' => (int) $campaignTotals->unsubscribed,
                'open_rate' => $this->rate($campaignTotals->opened, $campaignSent),
                'click_rate' => $this->rate($campaignTotals->clicked, $campaignSent),
                'recent' => $campaigns,
            ],
            'lead_magnets' => [
                'submissions' => DB::table('crm_lead_magnet_submissions')->where('company_id', $companyId)->whereBetween('created_at', $range)->count(),
                'items' => $magnets,
            ],
            'bookings' => [
                'total' => (clone $bookings)->whereBetween('created_at', $range)->count(),
                'upcoming' => (clone $bookings)->where('status', 'scheduled')->where('scheduled_at', '>=', now())->count(),
                'pages' => $bookingPages,
            ],
            'leads' => [
                'new' => (clone $leads)->count(),
                'converted' => (clone $leads)->where('status', 'converted')->count(),
                'by_source' => $sources,
            ],
            'daily' => $this->daily($companyId, $start, $end),
        ];
    }

    /** New leads, bookings and lead magnet sign-ups per day. */
    protected function daily(int $companyId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $count = fn (string $table, array $extra = []) => DB::table($table)
            ->where('company_id', $companyId)
            ->when($extra, fn ($q) => $q->where($extra))
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');

        $leads = $count('crm_leads');
        $signups = $count('crm_lead_magnet_submissions');
        $bookings = DB::table('crm_meetings')->where('company_id', $companyId)->whereNotNull('booking_page_id')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');

        $days = [];
        for ($day = $start; $day->lte($end) && count($days) < 400; $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'leads' => (int) ($leads[$key] ?? 0),
                'signups' => (int) ($signups[$key] ?? 0),
                'bookings' => (int) ($bookings[$key] ?? 0),
            ];
        }

        return $days;
    }

    protected function rate(int|string|null $part, int|string|null $whole): float
    {
        return (int) $whole > 0 ? round((int) $part / (int) $whole * 100, 1) : 0.0;
    }
}
