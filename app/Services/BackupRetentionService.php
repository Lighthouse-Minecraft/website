<?php

namespace App\Services;

use App\Models\SiteConfig;
use Carbon\Carbon;

class BackupRetentionService
{
    /**
     * Delete local backup files older than the configured retention window.
     *
     * @return string[] Paths of deleted files.
     */
    public function enforceLocalRetention(): array
    {
        $days = (int) SiteConfig::getValue('backup.local_retention_days', '7');
        $cutoff = Carbon::now()->subDays($days);
        $dir = storage_path('app/backups');
        $deleted = [];

        foreach (glob("{$dir}/*.sql.gz") ?: [] as $file) {
            if (Carbon::createFromTimestamp(filemtime($file))->lt($cutoff)) {
                @unlink($file);
                $deleted[] = $file;
            }
        }

        return $deleted;
    }

    /**
     * Enforce S3 retention tiers, deleting keys that fall outside:
     *   - 2 most recent uploads (3-day cadence)
     *   - 1 per week for the past 4 weeks
     *   - 1 per month for the past 3 months
     *
     * @return string[] Deleted S3 keys.
     */
    public function enforceS3Retention(BackupStorageService $storage): array
    {
        $files = $storage->list(); // sorted newest-first by filename timestamp

        if (empty($files)) {
            return [];
        }

        $keepKeys = collect();

        // Tier 1: 2 most recent
        $keepKeys = $keepKeys->merge(collect($files)->take(2));

        // Tier 2: 1 per 7-day window for the past 4 weeks
        for ($week = 1; $week <= 4; $week++) {
            $end = now()->subDays(($week - 1) * 7);
            $start = now()->subDays($week * 7);
            $match = collect($files)->first(
                fn ($k) => $storage->parseTimestamp($k)->between($start, $end)
            );
            if ($match !== null) {
                $keepKeys->push($match);
            }
        }

        // Tier 3: 1 per calendar month for the past 3 months
        for ($month = 1; $month <= 3; $month++) {
            $ref = now()->subMonths($month);
            $start = $ref->copy()->startOfMonth();
            $end = $ref->copy()->endOfMonth();
            $match = collect($files)->first(
                fn ($k) => $storage->parseTimestamp($k)->between($start, $end)
            );
            if ($match !== null) {
                $keepKeys->push($match);
            }
        }

        $keepKeys = $keepKeys->unique();
        $deleted = [];

        foreach ($files as $key) {
            if (! $keepKeys->contains($key)) {
                $storage->delete($key);
                $deleted[] = $key;
            }
        }

        return $deleted;
    }
}
