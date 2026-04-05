<?php

namespace App\Actions;

use App\Enums\DiscordAccountStatus;
use App\Enums\MinecraftAccountStatus;
use App\Models\DiscordAccount;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountLinkedNotification;
use App\Services\TicketNotificationService;
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
            'status' => DiscordAccountStatus::Active,
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

        $this->sendAccountLinkedNotifications($user, $account->username, 'Discord');

        return ['success' => true, 'message' => 'Discord account linked successfully.', 'account' => $account];
    }

    private function sendAccountLinkedNotifications(User $user, string $accountName, string $accountType): void
    {
        $activeMinecraft = $user->minecraftAccounts()->where('status', MinecraftAccountStatus::Active)->count();
        $disabledMinecraft = $user->minecraftAccounts()->where('status', '!=', MinecraftAccountStatus::Active->value)->count();
        $activeDiscord = $user->discordAccounts()->where('status', DiscordAccountStatus::Active)->count();
        $disabledDiscord = $user->discordAccounts()->where('status', '!=', DiscordAccountStatus::Active->value)->count();

        $notification = new AccountLinkedNotification(
            $user, $accountName, $accountType,
            $activeMinecraft, $disabledMinecraft, $activeDiscord, $disabledDiscord
        );

        $service = app(TicketNotificationService::class);

        // Notify parent (mail only, unconditional)
        foreach ($user->parents as $parent) {
            $parent->notify((clone $notification)->setChannels(['mail']));
        }

        // Notify staff with 'User - Manager' role via their staff_alerts preferences
        $userManagerRoleId = Role::where('name', 'User - Manager')->value('id');
        if ($userManagerRoleId) {
            $managers = User::whereHas('staffPosition.roles', fn ($r) => $r->where('roles.id', $userManagerRoleId))->get();
            foreach ($managers as $manager) {
                $service->send($manager, clone $notification, 'staff_alerts');
            }
        }
    }
}
