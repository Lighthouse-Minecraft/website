<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FakeDiscordApiService extends DiscordApiService
{
    public array $calls = [];

    public function getGuildMember(string $discordUserId): ?array
    {
        $this->calls[] = ['method' => 'getGuildMember', 'discord_user_id' => $discordUserId];
        Log::info('[FakeDiscord] getGuildMember', ['discord_user_id' => $discordUserId]);

        return [
            'user' => ['id' => $discordUserId, 'username' => 'fake_user'],
            'roles' => [],
        ];
    }

    public function addRole(string $discordUserId, string $roleId): bool
    {
        if (empty($roleId)) {
            return false;
        }

        $this->calls[] = ['method' => 'addRole', 'discord_user_id' => $discordUserId, 'role_id' => $roleId];
        Log::info('[FakeDiscord] addRole', ['discord_user_id' => $discordUserId, 'role_id' => $roleId]);

        return true;
    }

    public function removeRole(string $discordUserId, string $roleId): bool
    {
        if (empty($roleId)) {
            return false;
        }

        $this->calls[] = ['method' => 'removeRole', 'discord_user_id' => $discordUserId, 'role_id' => $roleId];
        Log::info('[FakeDiscord] removeRole', ['discord_user_id' => $discordUserId, 'role_id' => $roleId]);

        return true;
    }

    public function sendDirectMessage(string $discordUserId, string $content): bool
    {
        $this->calls[] = ['method' => 'sendDirectMessage', 'discord_user_id' => $discordUserId, 'content' => $content];
        Log::info('[FakeDiscord] sendDM', ['discord_user_id' => $discordUserId, 'content' => $content]);

        return true;
    }

    public function syncManagedRoles(string $discordUserId, array $managedRoleIds, array $desiredRoleIds): bool
    {
        $this->calls[] = [
            'method' => 'syncManagedRoles',
            'discord_user_id' => $discordUserId,
            'managed_role_ids' => $managedRoleIds,
            'desired_role_ids' => $desiredRoleIds,
        ];
        Log::info('[FakeDiscord] syncManagedRoles', [
            'discord_user_id' => $discordUserId,
            'managed_role_ids' => $managedRoleIds,
            'desired_role_ids' => $desiredRoleIds,
        ]);

        // Simulate the diff against an empty roles set so addRole/removeRole
        // calls are recorded for test assertions
        foreach (array_filter($desiredRoleIds) as $roleId) {
            $this->addRole($discordUserId, $roleId);
        }

        return true;
    }

    public function removeAllManagedRoles(string $discordUserId): void
    {
        $this->calls[] = ['method' => 'removeAllManagedRoles', 'discord_user_id' => $discordUserId];
        Log::info('[FakeDiscord] removeAllManagedRoles', ['discord_user_id' => $discordUserId]);
    }
}
