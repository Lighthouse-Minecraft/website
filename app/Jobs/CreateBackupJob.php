<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateBackupJob implements ShouldQueue
{
    use Queueable;

    public function handle(BackupService $service): void
    {
        $service->create();
    }
}
