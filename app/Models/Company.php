<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'currency_code',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
     * Resolves logo_url (a public-disk URL) back to a local filesystem path.
     * PDF rendering (dompdf) has enable_remote disabled, so images must be
     * embedded via a local file:// path rather than fetched over HTTP.
     */
    public function logoFilePath(): ?string
    {
        if (! $this->logo_url) {
            return null;
        }

        $path = parse_url($this->logo_url, PHP_URL_PATH);

        if (! $path) {
            return null;
        }

        $fullPath = public_path(ltrim($path, '/'));

        return is_file($fullPath) ? $fullPath : null;
    }
}
