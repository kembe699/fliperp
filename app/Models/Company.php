<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_platform' => 'boolean',
            'suspended_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
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
     * that case. Deliberately does NOT fall back to the app's own FlipErp brand
     * logo (public/logo.png) — that's this product's own mark, not the tenant's,
     * and would misrepresent whose document is being sent.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_url) {
            return null;
        }

        try {
            $publicBaseUrl = Storage::disk('public')->url('');

            if (! str_starts_with($this->logo_url, $publicBaseUrl)) {
                return null;
            }

            $path = Str::after($this->logo_url, $publicBaseUrl);

            if (! Storage::disk('public')->exists($path)) {
                return null;
            }

            $contents = Storage::disk('public')->get($path);
            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (\Throwable) {
            return null;
        }
    }
}
