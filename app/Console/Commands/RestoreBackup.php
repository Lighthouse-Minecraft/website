<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Notifications\RestoreCompletedNotification;
use App\Notifications\RestoreFailedNotification;
use App\Services\RestoreService;
use App\Services\TicketNotificationService;
use Illuminate\Console\Command;

class RestoreBackup extends Command
{
    protected $signature = 'app:backup-restore {filename : Bare filename inside storage/app/backups/}';

    protected $description = 'Restore database from a local backup file';

    public function handle(): int
    {
        $filename = $this->argument('filename');
        $path = storage_path("app/backups/{$filename}");

        $service = app(RestoreService::class);

        try {
            $service->restore($path);
            $this->info("Restore completed from: {$filename}");
            $this->notifyManagers('success', $filename);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Restore failed: {$e->getMessage()}");
            $this->notifyManagers('failure', $filename, $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function notifyManagers(string $outcome, string $filename, string $error = ''): void
    {
        $roleId = Role::where('name', 'Backup Manager')->value('id');

        if (! $roleId) {
            return;
        }

        $backupManagers = User::where(function ($q) use ($roleId) {
            $q->whereHas('staffPosition.roles', fn ($r) => $r->where('roles.id', $roleId))
                ->orWhereHas('staffPosition', fn ($p) => $p->whereNotNull('has_all_roles_at'))
                ->orWhereIn('staff_rank', function ($sub) use ($roleId) {
                    $sub->select('staff_rank')->from('role_staff_rank')->where('role_id', $roleId);
                });
        })->get();

        $notificationService = app(TicketNotificationService::class);

        foreach ($backupManagers as $manager) {
            $notification = $outcome === 'success'
                ? new RestoreCompletedNotification($filename)
                : new RestoreFailedNotification($error);

            $notificationService->send($manager, $notification, 'staff_alerts');
        }
    }
}
