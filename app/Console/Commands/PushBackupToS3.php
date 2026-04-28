<?php

namespace App\Console\Commands;

use App\Services\BackupRetentionService;
use App\Services\BackupStorageService;
use Illuminate\Console\Command;

class PushBackupToS3 extends Command
{
    protected $signature = 'app:backup-push-s3';

    protected $description = 'Push the most recent local backup to S3 and enforce S3 retention tiers';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        $files = glob("{$dir}/*.sql.gz") ?: [];

        if (empty($files)) {
            $this->error('No local backup files found.');

            return Command::FAILURE;
        }

        // Most recent by mtime
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));
        $localPath = $files[0];

        $storageService = app(BackupStorageService::class);
        $retentionService = app(BackupRetentionService::class);

        $key = $storageService->upload($localPath);
        $this->info("Uploaded: {$key}");

        $deleted = $retentionService->enforceS3Retention($storageService);

        if (! empty($deleted)) {
            foreach ($deleted as $d) {
                $this->info("Removed from S3: {$d}");
            }
            $this->info('Cleaned up '.count($deleted).' S3 backup(s).');
        }

        return Command::SUCCESS;
    }
}
