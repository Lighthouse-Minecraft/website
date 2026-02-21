<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\User;
use App\Notifications\UserReleasedFromBrigNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseUserFromBrig
{
    use AsAction;

    public function handle(User $target, User $admin, string $reason): void
    {
        $target->in_brig = false;
        $target->brig_reason = null;
        $target->brig_expires_at = null;
        $target->brig_timer_notified = false;
        $target->save();

        // Restore all banned Minecraft accounts
        foreach ($target->minecraftAccounts()->where('status', MinecraftAccountStatus::Banned->value)->get() as $account) {
            $account->status = MinecraftAccountStatus::Active;
            $account->save();
            SendMinecraftCommand::run(
                $account->whitelistAddCommand(),
                'whitelist',
                $account->command_id,
                $target
            );
        }

        SyncMinecraftRanks::run($target);

        RecordActivity::handle($target, 'user_released_from_brig', "Released from brig by {$admin->name}. Reason: {$reason}");

        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($target, new UserReleasedFromBrigNotification($target));
    }
}
