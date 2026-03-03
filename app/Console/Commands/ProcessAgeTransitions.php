<?php

namespace App\Console\Commands;

use App\Actions\ReleaseChildToAdult;
use App\Actions\ReleaseUserFromBrig;
use App\Enums\BrigType;
use App\Models\User;
use App\Notifications\AccountUnlockedNotification;
use App\Services\TicketNotificationService;
use Illuminate\Console\Command;

class ProcessAgeTransitions extends Command
{
    protected $signature = 'parent-portal:process-age-transitions';

    protected $description = 'Process age-based transitions: auto-release at 13 (no parent), auto-release at 19';

    public function handle(): void
    {
        $this->processThirteenYearOlds();
        $this->processNineteenYearOlds();
    }

    private function processThirteenYearOlds(): void
    {
        // Users who turned 13, are in parental_pending brig, and have no parent linked
        User::where('in_brig', true)
            ->where('brig_type', BrigType::ParentalPending)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', now()->subYears(13))
            ->whereDoesntHave('parents')
            ->chunkById(100, function ($users) {
                $notificationService = app(TicketNotificationService::class);

                foreach ($users as $user) {
                    try {
                        ReleaseUserFromBrig::run($user, $user, 'Automatically released: turned 13 with no parent registered.');

                        // Only send unlock notification and log if the user was actually released (not re-brigged by parental hold)
                        $user->refresh();
                        if (! $user->isInBrig()) {
                            $notificationService->send($user, new AccountUnlockedNotification, 'account');
                            $this->info("Released user {$user->name} (ID: {$user->id}) — turned 13, no parent.");
                        }
                    } catch (\Throwable $e) {
                        report($e);
                        $this->error("Failed to process user {$user->name} (ID: {$user->id}): {$e->getMessage()}");
                    }
                }
            });
    }

    private function processNineteenYearOlds(): void
    {
        // Users who turned 19 and still have parent links
        User::whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', now()->subYears(19))
            ->whereHas('parents')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    try {
                        ReleaseChildToAdult::run($user);
                        $this->info("Auto-released user {$user->name} (ID: {$user->id}) to adult account — turned 19.");
                    } catch (\Throwable $e) {
                        report($e);
                        $this->error("Failed to process user {$user->name} (ID: {$user->id}): {$e->getMessage()}");
                    }
                }
            });
    }
}
