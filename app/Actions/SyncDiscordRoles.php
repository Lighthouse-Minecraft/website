<?php

namespace App\Actions;

use App\Enums\MembershipLevel;
use App\Models\User;
use App\Services\DiscordApiService;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncDiscordRoles
{
    use AsAction;

    public function handle(User $user): void
    {
        $accounts = $user->discordAccounts()->active()->get();

        if ($accounts->isEmpty()) {
            return;
        }

        $discordApi = app(DiscordApiService::class);

        // Build the list of all managed role IDs (membership levels + verified)
        $managedRoleIds = [];
        foreach (MembershipLevel::cases() as $level) {
            $roleId = $level->discordRoleId();
            if ($roleId) {
                $managedRoleIds[] = $roleId;
            }
        }
        $verifiedRoleId = config('lighthouse.discord.roles.verified');
        if ($verifiedRoleId) {
            $managedRoleIds[] = $verifiedRoleId;
        }

        // Build the list of desired role IDs for this user
        $desiredRoleIds = array_filter([
            $user->membership_level->discordRoleId(),
            $verifiedRoleId,
        ]);

        foreach ($accounts as $account) {
            $discordApi->syncManagedRoles(
                $account->discord_user_id,
                $managedRoleIds,
                $desiredRoleIds
            );
        }

        RecordActivity::run(
            $user,
            'discord_roles_synced',
            "Synced Discord membership role to {$user->membership_level->label()}"
        );
    }
}
