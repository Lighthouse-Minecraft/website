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
        $days = max(1, (int) SiteConfig::getValue('backup.local_retention_days', '7'));
        $cutoff = Carbon::now()->subDays($days);
        $dir = storage_path('app/backups');
        $deleted = [];

        foreach (glob("{$dir}/*.sql.gz") ?: [] as $file) {
            if (Carbon::createFromTimestamp(filemtime($file))->lt($cutoff)) {
                if (@unlink($file)) {
                    $deleted[] = $file;
                }
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

        // Separate files into those with a parseable timestamp and those without.
        // Non-conforming filenames (no backup_YYYY-MM-DD pattern) are never auto-deleted.
        $conforming = array_filter($files, fn ($k) => preg_match('/backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}/', $k) === 1);
        $nonConforming = array_diff($files, $conforming);

        $keepKeys = collect($nonConforming); // always keep non-standard files

        // Tier 1: 2 most recent conforming
        $keepKeys = $keepKeys->merge(collect(array_values($conforming))->take(2));

        // Tier 2: 1 per 7-day window for the past 4 weeks
        for ($week = 1; $week <= 4; $week++) {
            $end = now()->subDays(($week - 1) * 7);
            $start = now()->subDays($week * 7);
            $match = collect($conforming)->first(
                fn ($k) => $storage->parseTimestamp($k)->between($start, $end)
            );
            if ($match !== null) {
                $keepKeys->push($match);
            }
        }

        // Tier 3: 1 per calendar month for the past 3 months.
        // Anchor to the 1st of the current month before subtracting so that
        // months with fewer days than today's date-of-month don't overflow
        // into the following month (e.g. April 29 - 2 months = Feb 28, not Mar 1).
        for ($month = 1; $month <= 3; $month++) {
            $ref = now()->startOfMonth()->subMonths($month);
            $start = $ref->copy();
            $end = $ref->copy()->endOfMonth();
            $match = collect($conforming)->first(
                fn ($k) => $storage->parseTimestamp($k)->between($start, $end)
            );
            if ($match !== null) {
                $keepKeys->push($match);
            }
        }

        $keepKeys = $keepKeys->unique();
        $deleted = [];

        foreach ($conforming as $key) {
            if (! $keepKeys->contains($key)) {
                $storage->delete($key);
                $deleted[] = $key;
            }
        }

        return $deleted;
    }
}
