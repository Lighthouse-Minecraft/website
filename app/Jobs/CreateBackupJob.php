<?php

namespace App\Jobs;

use App\Models\SiteConfig;
use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateBackupJob implements ShouldQueue
{
    use Queueable;

    public function handle(BackupService $service): void
    {
        SiteConfig::setValue('backup.last_job_status', 'running');
        SiteConfig::setValue('backup.last_job_updated_at', now()->toIso8601String());

        $path = $service->create();

        SiteConfig::setValue('backup.last_job_status', 'completed');
        SiteConfig::setValue('backup.last_job_updated_at', now()->toIso8601String());
        SiteConfig::setValue('backup.last_job_filename', basename($path));
    }

    public function failed(Throwable $e): void
    {
        SiteConfig::setValue('backup.last_job_status', 'failed');
        SiteConfig::setValue('backup.last_job_updated_at', now()->toIso8601String());
        SiteConfig::setValue('backup.last_job_filename', null);
    }
}
