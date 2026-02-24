<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\User;
use App\Notifications\UserPutInBrigNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class PutUserInBrig
{
    use AsAction;

    /**
     * Place a user in the brig and perform related side effects.
     *
     * Marks the target user as in brig (storing reason, optional expiry, and next-appeal timestamp), bans the user's active or verifying Minecraft accounts, records an activity entry, and notifies the user.
     *
     * @param  User  $target  The user to put in the brig.
     * @param  User  $admin  The admin performing the action.
     * @param  string  $reason  Reason for placing the user in the brig.
     * @param  Carbon|null  $expiresAt  Optional timestamp when the brig placement expires.
     * @param  Carbon|null  $appealAvailableAt  Optional timestamp when appeals become available.
     */
    public function handle(User $target, User $admin, string $reason, ?Carbon $expiresAt = null, ?Carbon $appealAvailableAt = null): void
    {
        $target->in_brig = true;
        $target->brig_reason = $reason;
        $target->brig_expires_at = $expiresAt;
        $target->next_appeal_available_at = $appealAvailableAt;
        $target->brig_timer_notified = false;
        $target->save();

        // Ban all active/verifying Minecraft accounts
        foreach ($target->minecraftAccounts()->whereIn('status', [MinecraftAccountStatus::Active, MinecraftAccountStatus::Verifying])->get() as $account) {
            SendMinecraftCommand::run(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $account->username,
                $admin
            );
            $account->status = MinecraftAccountStatus::Banned;
            $account->save();
        }

        // Strip Discord roles and mark accounts as brigged
        foreach ($target->discordAccounts()->active()->get() as $discordAccount) {
            try {
                $discordApi = app(\App\Services\DiscordApiService::class);
                $discordApi->removeAllManagedRoles($discordAccount->discord_user_id);
                $discordAccount->status = \App\Enums\DiscordAccountStatus::Brigged;
                $discordAccount->save();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to strip Discord roles during brig', [
                    'discord_user_id' => $discordAccount->discord_user_id,
                    'user_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $description = "Put in the brig by {$admin->name}. Reason: {$reason}.";
        if ($expiresAt) {
            $description .= " Timer set until {$expiresAt->toDateTimeString()}.";
        } else {
            $description .= ' No timer set.';
        }
        if ($appealAvailableAt) {
            $description .= " Appeals available after {$appealAvailableAt->toDateTimeString()}.";
        }

        RecordActivity::handle($target, 'user_put_in_brig', $description);

        $notificationService = app(TicketNotificationService::class);
        $notificationService->send($target, new UserPutInBrigNotification($target, $reason, $expiresAt));
    }
}
