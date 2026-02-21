<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BrigTimerExpiredNotification;
use App\Services\TicketNotificationService;
use Illuminate\Console\Command;

class CheckBrigTimers extends Command
{
    protected $signature = 'brig:check-timers';

    protected $description = 'Notify brig\'d users whose appeal timer has expired';

    /**
     * Notify users whose brig expiration time has passed and mark them as notified.
     *
     * Queries users with in_brig = true, a non-null brig_expires_at that is <= now(),
     * and brig_timer_notified = false; sends a BrigTimerExpiredNotification to each
     * matched user and updates their brig_timer_notified flag to true.
     *
     * @return int Command::SUCCESS on completion.
     */
    public function handle(): int
    {
        $users = User::where('in_brig', true)
            ->whereNotNull('brig_expires_at')
            ->where('brig_expires_at', '<=', now())
            ->where('brig_timer_notified', false)
            ->get();

        if ($users->isEmpty()) {
            $this->info('No expired brig timers to process.');

            return Command::SUCCESS;
        }

        $this->info("Processing {$users->count()} expired brig timer(s)...");

        $notificationService = app(TicketNotificationService::class);

        foreach ($users as $user) {
            $notificationService->send($user, new BrigTimerExpiredNotification($user));
            $user->update(['brig_timer_notified' => true]);
            $this->line("  Notified: {$user->name}");
        }

        $this->info('Done.');

        return Command::SUCCESS;
    }
}