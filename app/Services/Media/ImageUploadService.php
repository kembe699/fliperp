<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared upload/replace/delete logic for the "public"-disk image fields used
 * across the app (product images, company logo, ...). Centralizes the
 * store-then-return-URL and the delete-only-if-we-own-the-file behavior so
 * each feature doesn't reimplement it.
 */
class ImageUploadService
{
    /**
     * Stores the file under the given directory on the public disk and
     * returns its public URL. If a previous file is passed, it's deleted
     * first (but only if it's actually one of ours — see delete()).
     */
    public function replace(UploadedFile $file, string $directory, ?string $previousUrl = null): string
    {
        $this->delete($previousUrl);

        $path = $file->store($directory, 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * Deletes the file behind a stored public URL, but only if that URL
     * actually points at our own public storage — an externally linked URL
     * is left alone since we don't own that file.
     */
    public function delete(?string $url): void
    {
        if (! $url) {
            return;
        }

        $publicBaseUrl = Storage::disk('public')->url('');

        if (! str_starts_with($url, $publicBaseUrl)) {
            return;
        }

        $path = Str::after($url, $publicBaseUrl);
        Storage::disk('public')->delete($path);
    }
}
