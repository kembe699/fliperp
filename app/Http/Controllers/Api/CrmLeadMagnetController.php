<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadMagnet;
use App\Models\CrmLeadMagnetSubmission;
use App\Services\Crm\LeadMagnetService;
use App\Support\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Staff side of lead magnets (CRM > Lead Magnets). File uploads are multipart, so updates are POSTs. */
class CrmLeadMagnetController extends Controller
{
    public function __construct(protected LeadMagnetService $magnets) {}

    public function index(): JsonResponse
    {
        $items = CrmLeadMagnet::query()->withCount([
            'submissions',
            'submissions as downloads_count' => fn ($q) => $q->whereNotNull('downloaded_at'),
        ])->orderByDesc('created_at')->get();

        return $this->success($items->map(fn ($magnet) => $this->present($magnet)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $magnet = CrmLeadMagnet::create([
            ...collect($data)->except('file')->all(),
            'slug' => CrmBookingPageController::uniqueSlug(CrmLeadMagnet::class, $data['slug'] ?? null, $data['title']),
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('file')) {
            $this->magnets->storeFile($magnet, $request->file('file'));
        }
        $this->ensureHasContent($magnet);

        return $this->success($this->present($magnet->fresh()), 'Lead magnet created.', 201);
    }

    public function update(Request $request, CrmLeadMagnet $crmLeadMagnet): JsonResponse
    {
        $data = $this->validated($request, $crmLeadMagnet);
        $fields = collect($data)->except('file')->all();
        if (array_key_exists('slug', $fields)) {
            $fields['slug'] = CrmBookingPageController::uniqueSlug(CrmLeadMagnet::class, $fields['slug'], $fields['title'] ?? $crmLeadMagnet->title, $crmLeadMagnet->id);
        }
        $crmLeadMagnet->update($fields);

        if ($request->hasFile('file')) {
            $this->magnets->storeFile($crmLeadMagnet, $request->file('file'));
        }
        $this->ensureHasContent($crmLeadMagnet->fresh());

        return $this->success($this->present($crmLeadMagnet->fresh()), 'Lead magnet updated.');
    }

    public function destroy(CrmLeadMagnet $crmLeadMagnet): JsonResponse
    {
        $this->magnets->deleteFile($crmLeadMagnet);
        $crmLeadMagnet->delete();

        return $this->success(null, 'Lead magnet deleted.');
    }

    public function submissions(CrmLeadMagnet $crmLeadMagnet): JsonResponse
    {
        $rows = CrmLeadMagnetSubmission::query()
            ->where('lead_magnet_id', $crmLeadMagnet->id)
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                ...$row->only(['id', 'lead_id', 'name', 'email', 'phone', 'company_name', 'download_count']),
                'downloaded_at' => $row->downloaded_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return $this->success($rows);
    }

    protected function validated(Request $request, ?CrmLeadMagnet $magnet = null): array
    {
        $required = $magnet ? 'sometimes' : 'required';

        // Multipart forms send booleans as "1"/"0"/"true"/"false".
        foreach (['ask_phone', 'ask_company', 'is_active'] as $flag) {
            if ($request->has($flag)) {
                $request->merge([$flag => filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        return $request->validate([
            'title' => [$required, 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:60'], // turned into a URL-safe link by uniqueSlug()
            'headline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'thank_you_message' => ['nullable', 'string', 'max:2000'],
            'ask_phone' => ['sometimes', 'boolean'],
            'ask_company' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,png,jpg,jpeg,epub,mp3,mp4'],
        ]);
    }

    protected function ensureHasContent(CrmLeadMagnet $magnet): void
    {
        if (! $magnet->file_path && ! $magnet->external_url) {
            throw ValidationException::withMessages(['file' => ['Upload a file or give a link people get after signing up.']]);
        }
    }

    protected function present(CrmLeadMagnet $magnet): array
    {
        $submissions = (int) ($magnet->submissions_count ?? $magnet->submissions()->count());

        return [
            ...$magnet->only([
                'id', 'slug', 'title', 'headline', 'description', 'file_name', 'external_url', 'button_label',
                'thank_you_message', 'ask_phone', 'ask_company', 'is_active', 'views_count',
            ]),
            'has_file' => (bool) $magnet->file_path,
            'submissions_count' => $submissions,
            'downloads_count' => (int) ($magnet->downloads_count ?? $magnet->submissions()->whereNotNull('downloaded_at')->count()),
            'conversion_rate' => $magnet->views_count > 0 ? round($submissions / $magnet->views_count * 100, 1) : 0,
            'public_url' => Url::frontend("lm/{$magnet->slug}"),
            'created_at' => $magnet->created_at?->toIso8601String(),
        ];
    }
}
