<?php

namespace App\Jobs;

use App\Services\RestoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RestoreBackupJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $path) {}

    public function handle(RestoreService $service): void
    {
        $service->restore($this->path);
    }
}
