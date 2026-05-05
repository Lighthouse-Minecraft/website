<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Notifications\BackupCreatedNotification;
use App\Notifications\BackupFailedNotification;
use App\Services\BackupService;
use App\Services\TicketNotificationService;
use Illuminate\Console\Command;

class CreateBackup extends Command
{
    protected $signature = 'app:backup-create
                            {--skip-offline : Skip maintenance mode regardless of SiteConfig}';

    protected $description = 'Create a compressed database backup';

    public function handle(): int
    {
        $service = app(BackupService::class);

        if ($this->option('skip-offline')) {
            $service->setSkipOffline(true);
        }

        try {
            $path = $service->create();
            $this->info("Backup created: {$path}");
            $this->notifyManagers('success', $path);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup failed: {$e->getMessage()}");
            $this->notifyManagers('failure', null, $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function notifyManagers(string $outcome, ?string $path, string $error = ''): void
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
                ? new BackupCreatedNotification(basename($path))
                : new BackupFailedNotification($error);

            $notificationService->send($manager, $notification, 'staff_alerts');
        }
    }
}
