<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\User;
use App\Notifications\UserReleasedFromBrigNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseUserFromBrig
{
    use AsAction;

    /**
     * Releases a user from brig status and performs related cleanup, restoration, logging, and notification.
     *
     * This clears brig fields on the target user, reactivates any banned Minecraft accounts (issuing whitelist commands),
     * synchronizes Minecraft ranks, records an activity entry describing the release (including the admin and reason),
     * and sends a UserReleasedFromBrigNotification via the ticket notification service.
     *
     * @param  User  $target  The user being released from brig.
     * @param  User  $admin  The administrator performing the release.
     * @param  string  $reason  A human-readable reason for the release.
     */
    public function handle(User $target, User $admin, string $reason): void
    {
        $target->in_brig = false;
        $target->brig_reason = null;
        $target->brig_expires_at = null;
        $target->next_appeal_available_at = null;
        $target->brig_timer_notified = false;
        $target->save();

        // Restore all banned Minecraft accounts
        foreach ($target->minecraftAccounts()->where('status', MinecraftAccountStatus::Banned->value)->get() as $account) {
            try {
                SendMinecraftCommand::run(
                    $account->whitelistAddCommand(),
                    'whitelist',
                    $account->username,
                    $target
                );
                $account->status = MinecraftAccountStatus::Active;
                $account->save();
            } catch (\Exception $e) {
                Log::error('Failed to whitelist account on brig release', [
                    'username' => $account->username,
                    'user_id' => $target->id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        SyncMinecraftPermissions::run($target);

        RecordActivity::handle($target, 'user_released_from_brig', "Released from brig by {$admin->name}. Reason: {$reason}");

        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($target, new UserReleasedFromBrigNotification($target));
    }
}
