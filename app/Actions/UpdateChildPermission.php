<?php

namespace App\Actions;

use App\Enums\BrigType;
use App\Enums\DiscordAccountStatus;
use App\Enums\MinecraftAccountStatus;
use App\Models\User;
use App\Notifications\ParentAccountDisabledNotification;
use App\Notifications\ParentAccountEnabledNotification;
use App\Services\TicketNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateChildPermission
{
    use AsAction;

    public function handle(User $child, User $parent, string $permission, bool $enabled): void
    {
        match ($permission) {
            'use_site' => $this->toggleSiteAccess($child, $parent, $enabled),
            'login' => $this->toggleLogin($child, $parent, $enabled),
            'minecraft' => $this->toggleMinecraft($child, $parent, $enabled),
            'discord' => $this->toggleDiscord($child, $parent, $enabled),
            default => throw new \InvalidArgumentException("Unknown permission type: {$permission}"),
        };
    }

    private function toggleSiteAccess(User $child, User $parent, bool $enabled): void
    {
        $child->parent_allows_site = $enabled;
        $child->save();

        $notificationService = app(TicketNotificationService::class);

        if (! $enabled && ! $child->isInBrig()) {
            PutUserInBrig::run(
                target: $child,
                admin: $parent,
                reason: 'Account disabled by parent. All access including Minecraft and Discord is blocked.',
                brigType: BrigType::ParentalDisabled,
                notify: false,
            );
            $notificationService->send($child, new ParentAccountDisabledNotification($child, $parent), 'account');
        } elseif ($enabled && $child->isInBrig() && $child->brig_type?->isParental()) {
            ReleaseUserFromBrig::run($child, $parent, 'Site access enabled by parent.', notify: false);
            $notificationService->send($child, new ParentAccountEnabledNotification($child, $parent), 'account');
        }

        $action = $enabled ? 'enabled' : 'disabled';
        RecordActivity::run($child, 'parent_permission_changed', "Site access {$action} by parent {$parent->name}.");
    }

    private function toggleLogin(User $child, User $parent, bool $enabled): void
    {
        $child->parent_allows_login = $enabled;
        $child->save();

        if (! $enabled && config('session.driver') === 'database') {
            // Invalidate all active sessions for this child
            DB::table('sessions')->where('user_id', $child->id)->delete();
        }

        $action = $enabled ? 'enabled' : 'disabled';
        RecordActivity::run($child, 'parent_permission_changed', "Website login {$action} by parent {$parent->name}.");
    }

    private function toggleMinecraft(User $child, User $parent, bool $enabled): void
    {
        $child->parent_allows_minecraft = $enabled;
        $child->save();

        if (! $enabled) {
            foreach ($child->minecraftAccounts()->whereIn('status', [MinecraftAccountStatus::Active, MinecraftAccountStatus::Verifying])->get() as $account) {
                try {
                    $account->status = MinecraftAccountStatus::ParentDisabled;
                    $account->save();
                    SyncMinecraftAccount::run($account);
                } catch (\Exception $e) {
                    Log::warning('Failed to disable MC account for parent disable', [
                        'username' => $account->username,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            foreach ($child->minecraftAccounts()->where('status', MinecraftAccountStatus::ParentDisabled)->get() as $account) {
                try {
                    $account->status = MinecraftAccountStatus::Active;
                    $account->save();
                    SyncMinecraftAccount::run($account);
                } catch (\Exception $e) {
                    Log::warning('Failed to enable MC account for parent enable', [
                        'username' => $account->username,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $action = $enabled ? 'enabled' : 'disabled';
        RecordActivity::run($child, 'parent_permission_changed', "Minecraft access {$action} by parent {$parent->name}.");
    }

    private function toggleDiscord(User $child, User $parent, bool $enabled): void
    {
        $child->parent_allows_discord = $enabled;
        $child->save();

        if (! $enabled) {
            $discordApi = app(\App\Services\DiscordApiService::class);
            foreach ($child->discordAccounts()->where('status', DiscordAccountStatus::Active)->get() as $discordAccount) {
                try {
                    $discordApi->removeAllManagedRoles($discordAccount->discord_user_id);
                    $discordAccount->status = DiscordAccountStatus::ParentDisabled;
                    $discordAccount->save();
                } catch (\Exception $e) {
                    Log::warning('Failed to strip Discord roles for parent disable', [
                        'discord_user_id' => $discordAccount->discord_user_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            foreach ($child->discordAccounts()->where('status', DiscordAccountStatus::ParentDisabled)->get() as $discordAccount) {
                $discordAccount->status = DiscordAccountStatus::Active;
                $discordAccount->save();
            }

            try {
                SyncDiscordRoles::run($child);
            } catch (\Exception $e) {
                Log::error('Failed to sync Discord roles after parent enable', [
                    'user_id' => $child->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $action = $enabled ? 'enabled' : 'disabled';
        RecordActivity::run($child, 'parent_permission_changed', "Discord access {$action} by parent {$parent->name}.");
    }
}
