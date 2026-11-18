<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmEmailCampaign;
use App\Models\CrmLead;
use App\Services\Crm\EmailCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Email marketing campaigns (CRM > Campaigns). */
class CrmEmailCampaignController extends Controller
{
    public function __construct(protected EmailCampaignService $campaigns) {}

    public function index(): JsonResponse
    {
        $rows = CrmEmailCampaign::query()->with('createdBy:id,name')->orderByDesc('created_at')->limit(300)->get();

        return $this->success($rows->map(fn ($campaign) => $this->present($campaign)));
    }

    public function show(CrmEmailCampaign $crmEmailCampaign): JsonResponse
    {
        return $this->success($this->present($crmEmailCampaign->load('createdBy:id,name')));
    }

    public function store(Request $request): JsonResponse
    {
        $campaign = CrmEmailCampaign::create([
            ...$this->validated($request),
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return $this->success($this->present($campaign->load('createdBy:id,name')), 'Campaign saved as a draft.', 201);
    }

    public function update(Request $request, CrmEmailCampaign $crmEmailCampaign): JsonResponse
    {
        $this->ensureEditable($crmEmailCampaign);
        $crmEmailCampaign->update($this->validated($request, true));

        return $this->success($this->present($crmEmailCampaign->fresh('createdBy:id,name')), 'Campaign saved.');
    }

    public function destroy(CrmEmailCampaign $crmEmailCampaign): JsonResponse
    {
        if ($crmEmailCampaign->status === 'sending') {
            throw ValidationException::withMessages(['status' => ['Cancel the campaign before deleting it.']]);
        }
        $crmEmailCampaign->delete();

        return $this->success(null, 'Campaign deleted.');
    }

    /** How many people an audience reaches (before saving). */
    public function audiencePreview(Request $request): JsonResponse
    {
        $audience = $this->validateAudience($request);
        $people = $this->campaigns->resolveAudience($request->user()->company_id, $audience);

        return $this->success([
            'count' => $people->count(),
            'sample' => $people->take(5)->map(fn ($p) => ['name' => $p['name'], 'email' => $p['email']])->values(),
        ]);
    }

    /** Lead sources in use, for the audience picker. */
    public function leadSources(): JsonResponse
    {
        return $this->success(
            CrmLead::query()->whereNotNull('source')->where('source', '!=', '')->distinct()->orderBy('source')->limit(200)->pluck('source')
        );
    }

    public function sendTest(Request $request, CrmEmailCampaign $crmEmailCampaign): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $this->campaigns->sendTest($crmEmailCampaign, $data['email'], $request->user());

        return $this->success(null, "Test email sent to {$data['email']}.");
    }

    public function schedule(Request $request, CrmEmailCampaign $crmEmailCampaign): JsonResponse
    {
        $data = $request->validate(['scheduled_at' => ['nullable', 'date', 'after:now']]);
        $campaign = $this->campaigns->schedule($crmEmailCampaign, $data['scheduled_at'] ?? null);

        return $this->success(
            $this->present($campaign->load('createdBy:id,name')),
            $data['scheduled_at'] ?? null ? 'Campaign scheduled.' : 'Campaign is sending — the first emails go out within a minute.',
        );
    }

    public function cancel(CrmEmailCampaign $crmEmailCampaign): JsonResponse
    {
        $campaign = $this->campaigns->cancel($crmEmailCampaign);

        return $this->success($this->present($campaign->load('createdBy:id,name')), 'Campaign stopped.');
    }

    public function recipients(Request $request, CrmEmailCampaign $crmEmailCampaign): JsonResponse
    {
        $rows = $crmEmailCampaign->recipients()
            ->when($request->string('filter')->toString(), function ($q, $filter) {
                match ($filter) {
                    'opened' => $q->whereNotNull('opened_at'),
                    'clicked' => $q->whereNotNull('clicked_at'),
                    'unsubscribed' => $q->whereNotNull('unsubscribed_at'),
                    'failed' => $q->where('status', 'failed'),
                    default => null,
                };
            })
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated(JsonResource::collection($rows->through(fn ($r) => [
            ...$r->only(['id', 'email', 'name', 'lead_id', 'customer_id', 'status', 'error_message', 'open_count', 'click_count']),
            'sent_at' => $r->sent_at?->toIso8601String(),
            'opened_at' => $r->opened_at?->toIso8601String(),
            'clicked_at' => $r->clicked_at?->toIso8601String(),
            'unsubscribed_at' => $r->unsubscribed_at?->toIso8601String(),
        ])));
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'subject' => [$required, 'string', 'max:200'],
            'body' => [$required, 'string', 'max:50000'],
            'audience' => [$required, 'array'],
        ]);
        if (isset($data['audience'])) {
            $data['audience'] = $this->validateAudience($request);
        }

        return $data;
    }

    protected function validateAudience(Request $request): array
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'audience.type' => ['required', Rule::in(['leads', 'customers', 'all'])],
            'audience.lead_statuses' => ['nullable', 'array'],
            'audience.lead_statuses.*' => [Rule::in(['open', 'converted', 'disqualified'])],
            'audience.lead_sources' => ['nullable', 'array'],
            'audience.lead_sources.*' => ['string', 'max:255'],
            'audience.lead_magnet_id' => ['nullable', Rule::exists('crm_lead_magnets', 'id')->where('company_id', $companyId)],
        ]);

        return array_filter($data['audience'], fn ($value) => $value !== null && $value !== []);
    }

    protected function ensureEditable(CrmEmailCampaign $campaign): void
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw ValidationException::withMessages(['status' => ['A campaign that has been sent can no longer be edited.']]);
        }
    }

    protected function present(CrmEmailCampaign $campaign): array
    {
        return [
            ...$campaign->only(['id', 'name', 'subject', 'body', 'audience', 'status', 'recipients_count']),
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'finished_at' => $campaign->finished_at?->toIso8601String(),
            'created_by' => $campaign->createdBy ? ['id' => $campaign->createdBy->id, 'name' => $campaign->createdBy->name] : null,
            'stats' => $this->campaigns->stats($campaign),
            'created_at' => $campaign->created_at?->toIso8601String(),
        ];
    }
}
