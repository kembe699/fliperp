<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmBookingPage;
use App\Support\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Staff side of public booking pages (CRM > Calendar > Booking pages). */
class CrmBookingPageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = CrmBookingPage::query()->with('host:id,name')->withCount([
            'meetings as bookings_count',
            'meetings as upcoming_count' => fn ($q) => $q->where('status', 'scheduled')->where('scheduled_at', '>=', now()),
        ])->orderBy('title')->get();

        return $this->success($pages->map(fn ($page) => $this->present($page)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = self::uniqueSlug(CrmBookingPage::class, $data['slug'] ?? null, $data['title']);
        $data['company_id'] = $request->user()->company_id;
        $data['created_by'] = $request->user()->id;

        $page = CrmBookingPage::create($data);

        return $this->success($this->present($page->load('host:id,name')), 'Booking page created.', 201);
    }

    public function update(Request $request, CrmBookingPage $crmBookingPage): JsonResponse
    {
        $data = $this->validated($request, $crmBookingPage);
        if (array_key_exists('slug', $data)) {
            $data['slug'] = self::uniqueSlug(CrmBookingPage::class, $data['slug'], $data['title'] ?? $crmBookingPage->title, $crmBookingPage->id);
        }
        $crmBookingPage->update($data);

        return $this->success($this->present($crmBookingPage->fresh('host:id,name')), 'Booking page updated.');
    }

    public function destroy(CrmBookingPage $crmBookingPage): JsonResponse
    {
        $crmBookingPage->delete();

        return $this->success(null, 'Booking page deleted.');
    }

    protected function validated(Request $request, ?CrmBookingPage $page = null): array
    {
        $companyId = $request->user()->company_id;
        $required = $page ? 'sometimes' : 'required';

        $data = $request->validate([
            'title' => [$required, 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => [$required, 'integer', 'min:10', 'max:480'],
            'buffer_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'days' => [$required, 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'timezone' => [$required, 'timezone:all'],
            'min_notice_hours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'max_days_ahead' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'host_user_id' => [$required, Rule::exists('users', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $start = $data['start_time'] ?? $page?->start_time;
        $end = $data['end_time'] ?? $page?->end_time;
        if ($start && $end && $end <= $start) {
            throw ValidationException::withMessages(['end_time' => ['The day must end after it starts.']]);
        }
        if (isset($data['days'])) {
            $data['days'] = array_values(array_unique(array_map('intval', $data['days'])));
            sort($data['days']);
        }

        return $data;
    }

    protected function present(CrmBookingPage $page): array
    {
        return [
            ...$page->only([
                'id', 'slug', 'title', 'description', 'duration_minutes', 'buffer_minutes', 'days', 'start_time',
                'end_time', 'timezone', 'min_notice_hours', 'max_days_ahead', 'host_user_id', 'location',
                'meeting_link', 'is_active',
            ]),
            'host' => $page->host ? ['id' => $page->host->id, 'name' => $page->host->name] : null,
            'bookings_count' => (int) ($page->bookings_count ?? 0),
            'upcoming_count' => (int) ($page->upcoming_count ?? 0),
            'public_url' => Url::frontend("book/{$page->slug}"),
            'created_at' => $page->created_at?->toIso8601String(),
        ];
    }

    /** A URL slug unique across all companies (public links carry no company id). */
    public static function uniqueSlug(string $model, ?string $wanted, string $fallback, ?int $ignoreId = null): string
    {
        $base = Str::slug($wanted ?: $fallback) ?: 'page';
        $base = Str::limit($base, 50, '');
        $slug = $base;

        for ($i = 2; $model::query()->withoutGlobalScopes()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $i < 50 ? "{$base}-{$i}" : $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
