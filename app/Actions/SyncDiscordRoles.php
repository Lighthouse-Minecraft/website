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
        $currentRoleId = $user->membership_level->discordRoleId();

        foreach ($accounts as $account) {
            $discordUserId = $account->discord_user_id;

            // Remove all membership-level roles first
            foreach (MembershipLevel::cases() as $level) {
                $roleId = $level->discordRoleId();
                if ($roleId) {
                    $discordApi->removeRole($discordUserId, $roleId);
                }
            }

            // Add the correct membership role
            if ($currentRoleId) {
                $discordApi->addRole($discordUserId, $currentRoleId);
            }

            // Always add "verified" role if configured
            $verifiedRoleId = config('lighthouse.discord.roles.verified');
            if ($verifiedRoleId) {
                $discordApi->addRole($discordUserId, $verifiedRoleId);
            }
        }

        RecordActivity::run(
            $user,
            'discord_roles_synced',
            "Synced Discord membership role to {$user->membership_level->label()}"
        );
    }
}
