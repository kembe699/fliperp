<?php

namespace App\Services\Crm;

use App\Mail\CrmCampaignEmail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CrmEmailCampaign;
use App\Models\CrmEmailCampaignRecipient;
use App\Models\CrmEmailUnsubscribe;
use App\Models\CrmLead;
use App\Models\CrmLeadMagnetSubmission;
use App\Models\User;
use App\Support\EmailText;
use App\Support\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Email marketing. A campaign is written once, then sent to its audience by
 * the scheduler (crm:send-campaigns, every minute) in small batches — the
 * droplet has no queue worker and the mail provider rate-limits, so a large
 * list goes out over a few minutes instead of inside one web request.
 *
 * Everything here names the company explicitly: the scheduler and the public
 * tracking links run with no signed-in user, so no CompanyScope applies.
 */
class EmailCampaignService
{
    /** Merge fields available in a campaign's subject and body. */
    public const MERGE_FIELDS = ['{{name}}', '{{first_name}}', '{{company}}'];

    /**
     * Who a campaign would go to: leads and/or customers with an email,
     * de-duplicated by address, without anyone who unsubscribed.
     *
     * @return Collection<int, array{email: string, name: ?string, lead_id: ?int, customer_id: ?int}>
     */
    public function resolveAudience(int $companyId, array $audience): Collection
    {
        $type = $audience['type'] ?? 'leads';
        $people = collect();

        if (in_array($type, ['leads', 'all'], true)) {
            $leads = CrmLead::query()
                ->where('company_id', $companyId)
                ->whereNotNull('email')->where('email', '!=', '')
                ->when($audience['lead_statuses'] ?? null, fn ($q, $statuses) => $q->whereIn('status', $statuses))
                ->when($audience['lead_sources'] ?? null, fn ($q, $sources) => $q->whereIn('source', $sources))
                ->when($audience['lead_magnet_id'] ?? null, fn ($q, $magnetId) => $q->whereIn(
                    'id',
                    CrmLeadMagnetSubmission::query()->where('company_id', $companyId)
                        ->where('lead_magnet_id', $magnetId)->whereNotNull('lead_id')->select('lead_id'),
                ))
                ->get(['id', 'name', 'email']);

            foreach ($leads as $lead) {
                $people->push(['email' => $lead->email, 'name' => $lead->name, 'lead_id' => $lead->id, 'customer_id' => null]);
            }
        }

        if (in_array($type, ['customers', 'all'], true)) {
            $customers = Customer::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNotNull('email')->where('email', '!=', '')
                ->get(['id', 'name', 'email']);

            foreach ($customers as $customer) {
                $people->push(['email' => $customer->email, 'name' => $customer->name, 'lead_id' => null, 'customer_id' => $customer->id]);
            }
        }

        $unsubscribed = CrmEmailUnsubscribe::query()->where('company_id', $companyId)->pluck('email')
            ->map(fn ($email) => Str::lower($email))->flip();

        return $people
            ->map(fn ($person) => [...$person, 'email' => Str::lower(trim($person['email']))])
            ->filter(fn ($person) => filter_var($person['email'], FILTER_VALIDATE_EMAIL) && ! isset($unsubscribed[$person['email']]))
            ->unique('email')
            ->values();
    }

    /** Queues the campaign to go out at $at (now when null); the scheduler sends it. */
    public function schedule(CrmEmailCampaign $campaign, ?string $at = null): CrmEmailCampaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw ValidationException::withMessages(['status' => ['This campaign has already been sent.']]);
        }

        if ($this->resolveAudience($campaign->company_id, $campaign->audience ?? [])->isEmpty()) {
            throw ValidationException::withMessages(['audience' => ['Nobody in this audience has an email address (or they all unsubscribed).']]);
        }

        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $at ?? now()]);

        return $campaign->fresh();
    }

    public function cancel(CrmEmailCampaign $campaign): CrmEmailCampaign
    {
        if ($campaign->status === 'scheduled') {
            $campaign->update(['status' => 'draft', 'scheduled_at' => null]);
        } elseif ($campaign->status === 'sending') {
            // Stops the remaining batches; whoever already got it keeps it.
            $campaign->recipients()->where('status', 'pending')->delete();
            $campaign->update([
                'status' => 'cancelled',
                'finished_at' => now(),
                'recipients_count' => $campaign->recipients()->count(),
            ]);
        }

        return $campaign->fresh();
    }

    /**
     * One scheduler tick: start campaigns that are due, then send up to $limit
     * emails across the campaigns that are sending. Returns how many were sent.
     */
    public function processDue(int $limit = 40, int $pauseMs = 550): int
    {
        CrmEmailCampaign::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get()
            ->each(fn (CrmEmailCampaign $campaign) => $this->start($campaign));

        $processed = 0;
        $campaigns = CrmEmailCampaign::query()->where('status', 'sending')->orderBy('started_at')->get();

        foreach ($campaigns as $campaign) {
            $company = Company::find($campaign->company_id);
            $pending = $campaign->recipients()->where('status', 'pending')->orderBy('id')->limit($limit - $processed)->get();

            foreach ($pending as $recipient) {
                if ($processed > 0 && $pauseMs > 0) {
                    usleep($pauseMs * 1000); // the mail provider allows ~2 requests a second
                }
                $this->deliver($campaign, $recipient, $company);
                $processed++;
            }

            if (! $campaign->recipients()->where('status', 'pending')->exists()) {
                $campaign->update(['status' => 'sent', 'finished_at' => now()]);
            }

            if ($processed >= $limit) {
                break;
            }
        }

        return $processed;
    }

    /** Sends the campaign to one address now, marked [TEST], without tracking. */
    public function sendTest(CrmEmailCampaign $campaign, string $toEmail, ?User $sender = null): void
    {
        $company = Company::find($campaign->company_id);
        $sample = ['name' => $sender?->name ?? 'Test Recipient'];

        Mail::to($toEmail)->send(new CrmCampaignEmail(
            subjectLine: '[TEST] '.$this->merge($campaign->subject, $sample, $company),
            bodyHtml: EmailText::toHtml($this->merge($campaign->body, $sample, $company)),
            companyName: $company?->name ?? config('app.name'),
            replyToEmail: $sender?->email ?? $this->replyTo($campaign),
        ));
    }

    /** @return array<string, int|float> */
    public function stats(CrmEmailCampaign $campaign): array
    {
        $row = DB::table('crm_email_campaign_recipients')
            ->where('campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'sent') AS sent,
                COUNT(*) FILTER (WHERE status = 'failed') AS failed,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE opened_at IS NOT NULL) AS opened,
                COUNT(*) FILTER (WHERE clicked_at IS NOT NULL) AS clicked,
                COUNT(*) FILTER (WHERE unsubscribed_at IS NOT NULL) AS unsubscribed
            ")
            ->first();

        $sent = (int) $row->sent;

        return [
            'total' => (int) $row->total,
            'sent' => $sent,
            'failed' => (int) $row->failed,
            'pending' => (int) $row->pending,
            'opened' => (int) $row->opened,
            'clicked' => (int) $row->clicked,
            'unsubscribed' => (int) $row->unsubscribed,
            'open_rate' => $sent ? round($row->opened / $sent * 100, 1) : 0,
            'click_rate' => $sent ? round($row->clicked / $sent * 100, 1) : 0,
        ];
    }

    // --- Public tracking (no login) ---

    public function recordOpen(string $token): void
    {
        $recipient = CrmEmailCampaignRecipient::query()->where('token', $token)->first();
        if (! $recipient) {
            return;
        }
        $recipient->forceFill([
            'opened_at' => $recipient->opened_at ?? now(),
            'open_count' => $recipient->open_count + 1,
        ])->save();
    }

    /** Records the click and returns where to send the person, or null for a forged link. */
    public function recordClick(string $token, string $url, string $signature): ?string
    {
        if (! hash_equals($this->signLink($token, $url), $signature) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $recipient = CrmEmailCampaignRecipient::query()->where('token', $token)->first();
        if ($recipient) {
            $recipient->forceFill([
                'clicked_at' => $recipient->clicked_at ?? now(),
                'click_count' => $recipient->click_count + 1,
                // A click means the email was opened, even with images blocked.
                'opened_at' => $recipient->opened_at ?? now(),
            ])->save();
        }

        return $url;
    }

    /** @return array{email: string, company: string}|null */
    public function unsubscribe(string $token): ?array
    {
        $recipient = CrmEmailCampaignRecipient::query()->where('token', $token)->first();
        if (! $recipient) {
            return null;
        }

        $recipient->forceFill(['unsubscribed_at' => $recipient->unsubscribed_at ?? now()])->save();
        CrmEmailUnsubscribe::query()->firstOrCreate(
            ['company_id' => $recipient->company_id, 'email' => Str::lower($recipient->email)],
            ['campaign_id' => $recipient->campaign_id],
        );

        return [
            'email' => $recipient->email,
            'company' => Company::find($recipient->company_id)?->name ?? config('app.name'),
        ];
    }

    // --- Internals ---

    protected function start(CrmEmailCampaign $campaign): void
    {
        $people = $this->resolveAudience($campaign->company_id, $campaign->audience ?? []);
        $now = now();

        foreach ($people->chunk(500) as $chunk) {
            CrmEmailCampaignRecipient::query()->insertOrIgnore($chunk->map(fn ($person) => [
                'company_id' => $campaign->company_id,
                'campaign_id' => $campaign->id,
                'lead_id' => $person['lead_id'],
                'customer_id' => $person['customer_id'],
                'email' => $person['email'],
                'name' => $person['name'],
                'token' => Str::random(48),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => $now,
            'recipients_count' => $campaign->recipients()->count(),
        ]);
    }

    protected function deliver(CrmEmailCampaign $campaign, CrmEmailCampaignRecipient $recipient, ?Company $company): void
    {
        $person = ['name' => $recipient->name];

        try {
            $bodyHtml = EmailText::toHtml(
                $this->merge($campaign->body, $person, $company),
                fn (string $url) => Url::api("public/email/click/{$recipient->token}?".http_build_query([
                    'u' => $url,
                    's' => $this->signLink($recipient->token, $url),
                ])),
            );

            Mail::to($recipient->email, $recipient->name)->send(new CrmCampaignEmail(
                subjectLine: $this->merge($campaign->subject, $person, $company),
                bodyHtml: $bodyHtml,
                companyName: $company?->name ?? config('app.name'),
                unsubscribeUrl: Url::frontend("unsubscribe/{$recipient->token}"),
                pixelUrl: Url::api("public/email/open/{$recipient->token}"),
                replyToEmail: $this->replyTo($campaign),
                oneClickUnsubscribeUrl: Url::api("public/email/unsubscribe/{$recipient->token}"),
            ));

            $recipient->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            report($e);
            $recipient->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)]);
        }
    }

    /** @var array<int, ?string> */
    protected array $replyTo = [];

    /** Replies go to whoever created the campaign. */
    protected function replyTo(CrmEmailCampaign $campaign): ?string
    {
        return $this->replyTo[$campaign->id] ??= User::query()->find($campaign->created_by)?->email;
    }

    protected function merge(string $text, array $person, ?Company $company): string
    {
        $name = trim((string) ($person['name'] ?? '')) ?: 'there';

        return strtr($text, [
            '{{name}}' => $name,
            '{{first_name}}' => Str::before($name, ' '),
            '{{company}}' => $company?->name ?? '',
        ]);
    }

    protected function signLink(string $token, string $url): string
    {
        return substr(hash_hmac('sha256', $token.'|'.$url, (string) config('app.key')), 0, 32);
    }
}
