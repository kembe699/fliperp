<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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
     * Resolves logo_url (a public-disk URL) back to a local filesystem path.
     * PDF rendering (dompdf) has enable_remote disabled, so images must be
     * embedded via a local file:// path rather than fetched over HTTP.
     *
     * Falls back to the app's own bundled brand logo (public/logo.png, committed
     * to git) whenever the company hasn't uploaded one, or an uploaded one is
     * missing on disk (e.g. wiped by a deploy platform with no persistent
     * storage across releases) — every generated document should carry a logo,
     * not silently fall back to plain text.
     */
    public function logoFilePath(): ?string
    {
        if ($this->logo_url) {
            $path = parse_url($this->logo_url, PHP_URL_PATH);
            $fullPath = $path ? public_path(ltrim($path, '/')) : null;

            if ($fullPath && is_file($fullPath)) {
                return $fullPath;
            }
        }

        $default = public_path('logo.png');

        return is_file($default) ? $default : null;
    }
}
