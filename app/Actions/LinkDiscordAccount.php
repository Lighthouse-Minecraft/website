<?php

namespace App\Actions;

use App\Models\DiscordAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkDiscordAccount
{
    use AsAction;

    /**
     * @return array{success: bool, message: string, account?: DiscordAccount}
     */
    public function handle(User $user, array $discordData): array
    {
        $maxAccounts = config('lighthouse.max_discord_accounts', 1);
        if ($user->discordAccounts()->count() >= $maxAccounts) {
            return ['success' => false, 'message' => 'Maximum Discord accounts reached.'];
        }

        // Check if this Discord account is already linked to any user
        $existing = DiscordAccount::where('discord_user_id', $discordData['id'])->first();
        if ($existing) {
            return ['success' => false, 'message' => 'This Discord account is already linked to another user.'];
        }

        $account = $user->discordAccounts()->create([
            'discord_user_id' => $discordData['id'],
            'username' => $discordData['username'] ?? $discordData['nickname'] ?? 'Unknown',
            'global_name' => $discordData['global_name'] ?? null,
            'avatar_hash' => $discordData['avatar'] ?? null,
            'access_token' => $discordData['access_token'],
            'refresh_token' => $discordData['refresh_token'] ?? null,
            'token_expires_at' => isset($discordData['expires_in'])
                ? Carbon::now()->addSeconds($discordData['expires_in'])
                : null,
            'status' => 'active',
            'verified_at' => Carbon::now(),
        ]);

        // Sync Discord membership roles for this user
        SyncDiscordRoles::run($user);

        // Sync staff roles only if the user is actually staff
        if ($user->staff_department !== null) {
            SyncDiscordStaff::run($user, $user->staff_department);
        }

        RecordActivity::run(
            $user,
            'discord_account_linked',
            "Linked Discord account: {$account->username} ({$account->discord_user_id})"
        );

        return ['success' => true, 'message' => 'Discord account linked successfully.', 'account' => $account];
    }
}
