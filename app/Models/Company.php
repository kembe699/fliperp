<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Modules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Brand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Company extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'client_code',
        'logo_url',
        'currency_code',
        'timezone',
        'is_active',
        'status',
        'is_platform',
        'billing_customer_id',
        'onboarded_by',
        'suspended_at',
        'activated_at',
        'enabled_modules',
        'allow_self_approval',
        'reporting_mode',
        'reporting_options',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_platform' => 'boolean',
            'suspended_at' => 'datetime',
            'activated_at' => 'datetime',
            'enabled_modules' => 'array',
            'allow_self_approval' => 'boolean',
            'reporting_options' => 'array',
        ];
    }

    /** Statements presented the not-for-profit way (config/reporting.php). */
    public function isNonprofit(): bool
    {
        return $this->reporting_mode === 'nonprofit';
    }

    /** A reporting option: this company's override, else its mode's default. */
    public function reportingOption(string $key): mixed
    {
        $mode = in_array($this->reporting_mode, config('reporting.modes'), true) ? $this->reporting_mode : 'standard';

        return ($this->reporting_options ?? [])[$key] ?? config("reporting.{$mode}.{$key}");
    }

    /**
     * Permission groups this company's switched-off modules take away (see
     * App\Support\Modules). Empty for a company with every module on.
     *
     * @return array<int, string>
     */
    public function disabledPermissionGroups(): array
    {
        return Modules::disabledGroups($this->enabled_modules);
    }

    /** @return array<int, string> */
    public function enabledModules(): array
    {
        return Modules::enabledFor($this->enabled_modules);
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (! $company->client_code) {
                $company->client_code = static::generateClientCode();
            }
        });
    }

    /**
     * NHC-XXXXX, unique across all companies (including soft-deleted ones — a
     * once-used code should never be reissued to a different client).
     */
    public static function generateClientCode(): string
    {
        do {
            $code = 'NHC-'.strtoupper(Str::random(5));
        } while (DB::table('companies')->where('client_code', $code)->exists());

        return $code;
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The customer record inside the PLATFORM company's own books that this
     * client is billed against — set once at onboarding. Only ever populated
     * on non-platform (client) companies.
     */
    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'billing_customer_id');
    }

    public function onboardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onboarded_by');
    }

    public function platformTickets(): HasMany
    {
        return $this->hasMany(PlatformTicket::class);
    }

    /**
     * Reads the company's own uploaded logo (via the "public" disk — local in dev,
     * S3/R2 in production, see config/filesystems.php) and returns it as a base64
     * data: URI. PDF rendering (dompdf) has enable_remote disabled so it can't fetch
     * the logo_url itself over HTTP, but a pre-fetched data: URI is just an inline
     * string, not a network request, so dompdf renders it fine regardless of which
     * disk driver actually stores the file — unlike resolving logo_url back to a
     * local filesystem path, which only ever works when the disk happens to be
     * local AND the storage:link symlink exists in that runtime.
     *
     * Returns null when the company hasn't uploaded a logo (or it can't be read) —
     * document-header.blade.php falls back to printing the company name as text in
     * that case. This returns only the company's OWN logo; documents use
     * documentLogoDataUri(), which falls back to the FlipERP logo and then also
     * prints the company name, so it is always clear whose document it is.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_url) {
            return null;
        }

        try {
            $publicBaseUrl = Storage::disk('public')->url('');

            if (str_starts_with($this->logo_url, $publicBaseUrl)) {
                $path = Str::after($this->logo_url, $publicBaseUrl);

                if (! Storage::disk('public')->exists($path)) {
                    return null;
                }

                $contents = Storage::disk('public')->get($path);
                $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

                return 'data:'.$mime.';base64,'.base64_encode($contents);
            }

            // Logos uploaded before a storage move (e.g. to the old R2 bucket) still
            // live at that address: fetch them, from known logo hosts only.
            return $this->remoteLogoDataUri($this->logo_url);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The logo printed on the company's documents: its own logo, or FlipERP's
     * when the company hasn't uploaded one (or it can't be read).
     */
    public function documentLogoDataUri(): ?string
    {
        return $this->logoDataUri() ?? Brand::logoDataUri();
    }

    /**
     * Fetches a logo kept outside the public disk and returns it as a data:
     * URI, cached for a day so each PDF doesn't download it again. Only https
     * addresses on an allowed host (config filesystems.logo_hosts), only
     * images, at most 2 MB, with a short timeout — so a bad logo_url can
     * neither point the server at an arbitrary address nor slow a PDF down.
     */
    protected function remoteLogoDataUri(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || ! $this->isAllowedLogoHost($host)) {
            return null;
        }

        $cached = Cache::remember('company-logo:'.sha1($url), now()->addDay(), function () use ($url) {
            $response = Http::timeout(5)->withOptions(['allow_redirects' => false])->get($url);
            $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $body = $response->body();

            if (! $response->successful() || ! in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true) || strlen($body) === 0 || strlen($body) > 2 * 1024 * 1024) {
                return '';
            }

            return 'data:'.$mime.';base64,'.base64_encode($body);
        });

        return $cached ?: null;
    }

    protected function isAllowedLogoHost(string $host): bool
    {
        foreach ((array) config('filesystems.logo_hosts', []) as $allowed) {
            $allowed = strtolower(trim((string) $allowed));
            if ($allowed === '') {
                continue;
            }
            if (str_starts_with($allowed, '*.') ? str_ends_with($host, substr($allowed, 1)) : $host === $allowed) {
                return true;
            }
        }

        return false;
    }
}
