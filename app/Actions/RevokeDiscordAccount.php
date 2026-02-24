<?php

namespace App\Actions;

use App\Models\DiscordAccount;
use App\Models\User;
use App\Services\DiscordApiService;
use Lorisleiva\Actions\Concerns\AsAction;

class RevokeDiscordAccount
{
    use AsAction;

    public function handle(DiscordAccount $account, User $admin): void
    {
        $discordApi = app(DiscordApiService::class);
        $discordApi->removeAllManagedRoles($account->discord_user_id);

        $username = $account->username;
        $discordUserId = $account->discord_user_id;
        $owner = $account->user;

        $account->delete();

        RecordActivity::run(
            $owner,
            'discord_account_revoked',
            "Discord account revoked by {$admin->name}: {$username} ({$discordUserId})"
        );
    }
}
