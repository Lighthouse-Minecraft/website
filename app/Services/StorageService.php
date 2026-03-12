<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class StorageService
{
    /**
     * Generate a URL for a file on the public storage disk.
     *
     * Uses temporary signed URLs for S3 (private bucket) and
     * falls back to permanent URLs for local disk (dev environment).
     */
    public static function publicUrl(string $path, int $expirationMinutes = 60): string
    {
        $disk = Storage::disk(config('filesystems.public_disk'));

        if ($disk->getConfig('driver') === 's3') {
            return $disk->temporaryUrl($path, now()->addMinutes($expirationMinutes));
        }

        return $disk->url($path);
    }
}
