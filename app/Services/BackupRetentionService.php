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
}
