<?php

namespace App\Console\Commands;

use App\Services\BackupRetentionService;
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

        return Command::SUCCESS;
    }
}
