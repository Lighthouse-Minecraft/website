<?php

namespace App\Jobs;

use App\Services\RestoreService;
use App\Services\RestoreStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RestoreBackupJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $path) {}

    public function handle(RestoreService $service, RestoreStatusService $statusService): void
    {
        $statusService->set('running', ['started_at' => now()->toIso8601String()]);

        try {
            $service->restore($this->path);
            $statusService->set('completed', ['completed_at' => now()->toIso8601String()]);
        } catch (\Throwable $e) {
            $statusService->set('failed', [
                'error' => $e->getMessage(),
                'completed_at' => now()->toIso8601String(),
            ]);
            throw $e;
        }
    }
}
