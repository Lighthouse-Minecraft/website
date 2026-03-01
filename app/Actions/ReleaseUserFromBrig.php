<?php

namespace App\Actions;

use App\Enums\BrigType;
use App\Enums\DiscordAccountStatus;
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
        $target->brig_type = null;
        $target->save();

        // Determine MC restoration status based on parent toggle
        $mcRestoreStatus = $target->parent_allows_minecraft
            ? MinecraftAccountStatus::Active
            : MinecraftAccountStatus::ParentDisabled;

        // Restore all banned Minecraft accounts
        foreach ($target->minecraftAccounts()->where('status', MinecraftAccountStatus::Banned->value)->get() as $account) {
            try {
                if ($mcRestoreStatus === MinecraftAccountStatus::Active) {
                    SendMinecraftCommand::run(
                        $account->whitelistAddCommand(),
                        'whitelist',
                        $account->username,
                        $target
                    );
                }
                $account->status = $mcRestoreStatus;
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

        if ($mcRestoreStatus === MinecraftAccountStatus::Active) {
            try {
                SyncMinecraftRanks::run($target);
            } catch (\Exception $e) {
                Log::error('Failed to sync Minecraft ranks on brig release', [
                    'user_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            }
            if ($target->staff_department !== null) {
                try {
                    SyncMinecraftStaff::run($target, $target->staff_department);
                } catch (\Exception $e) {
                    Log::error('Failed to sync Minecraft staff on brig release', [
                        'user_id' => $target->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Determine Discord restoration status based on parent toggle
        $discordRestoreStatus = $target->parent_allows_discord
            ? DiscordAccountStatus::Active
            : DiscordAccountStatus::ParentDisabled;

        // Restore Discord accounts from brigged
        foreach ($target->discordAccounts()->where('status', DiscordAccountStatus::Brigged)->get() as $discordAccount) {
            try {
                $discordAccount->status = $discordRestoreStatus;
                $discordAccount->save();
            } catch (\Exception $e) {
                Log::error('Failed to restore Discord account on brig release', [
                    'discord_user_id' => $discordAccount->discord_user_id,
                    'user_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($discordRestoreStatus === DiscordAccountStatus::Active) {
            try {
                SyncDiscordRoles::run($target);
            } catch (\Exception $e) {
                Log::error('Failed to sync Discord roles on brig release', [
                    'user_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            }
            if ($target->staff_department !== null) {
                try {
                    SyncDiscordStaff::run($target, $target->staff_department);
                } catch (\Exception $e) {
                    Log::error('Failed to sync Discord staff on brig release', [
                        'user_id' => $target->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        RecordActivity::handle($target, 'user_released_from_brig', "Released from brig by {$admin->name}. Reason: {$reason}");

        // Check if parental hold should re-engage after discipline release
        if (! $target->parent_allows_site && $target->isMinor()) {
            $target->in_brig = true;
            $target->brig_type = BrigType::ParentalDisabled;
            $target->brig_reason = 'Site access restricted by parent.';
            $target->brig_expires_at = null;
            $target->next_appeal_available_at = null;
            $target->save();

            return;
        }

        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($target, new UserReleasedFromBrigNotification($target), 'account');
    }
}
