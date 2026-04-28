<?php

namespace App\Console\Commands;

use App\Services\BackupRetentionService;
use App\Services\BackupStorageService;
use Illuminate\Console\Command;

class CleanupBackups extends Command
{
    protected $signature = 'app:backup-cleanup';

    protected $description = 'Delete local backup files older than the configured retention window';

    public function handle(): int
    {
        $service = app(BackupRetentionService::class);
        $deleted = $service->enforceLocalRetention();

        if (empty($deleted)) {
            $this->info('No backup files to clean up.');
        } else {
            foreach ($deleted as $path) {
                $this->info('Deleted: '.basename($path));
            }
            $this->info('Cleaned up '.count($deleted).' backup file(s).');
        }

        // Also enforce S3 retention when S3 is configured.
        if (config('filesystems.disks.s3.key')) {
            try {
                $storageService = app(BackupStorageService::class);
                $s3Deleted = $service->enforceS3Retention($storageService);
                if (! empty($s3Deleted)) {
                    $this->info('Cleaned up '.count($s3Deleted).' S3 backup(s).');
                }
            } catch (\Throwable $e) {
                $this->warn('S3 retention failed: '.$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
