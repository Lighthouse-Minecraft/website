<?php

namespace App\Actions;

use App\Enums\BrigType;
use App\Enums\DiscordAccountStatus;
use App\Enums\MinecraftAccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateChildPermission
{
    use AsAction;

    public function handle(User $child, User $parent, string $permission, bool $enabled): void
    {
        match ($permission) {
            'use_site' => $this->toggleSiteAccess($child, $parent, $enabled),
            'minecraft' => $this->toggleMinecraft($child, $parent, $enabled),
            'discord' => $this->toggleDiscord($child, $parent, $enabled),
        };
    }

    private function toggleSiteAccess(User $child, User $parent, bool $enabled): void
    {
        $child->parent_allows_site = $enabled;
        $child->save();

        if (! $enabled && ! $child->isInBrig()) {
            PutUserInBrig::run(
                target: $child,
                admin: $parent,
                reason: 'Site access restricted by parent.',
                brigType: BrigType::ParentalDisabled,
                notify: false,
            );
        } elseif ($enabled && $child->isInBrig() && $child->brig_type?->isParental()) {
            ReleaseUserFromBrig::run($child, $parent, 'Site access enabled by parent.');
        }

        $action = $enabled ? 'enabled' : 'disabled';
        RecordActivity::run($child, 'parent_permission_changed', "Site access {$action} by parent {$parent->name}.");
    }

    private function toggleMinecraft(User $child, User $parent, bool $enabled): void
    {
        $child->parent_allows_minecraft = $enabled;
        $child->save();

        if (! $enabled) {
            foreach ($child->minecraftAccounts()->whereIn('status', [MinecraftAccountStatus::Active, MinecraftAccountStatus::Verifying])->get() as $account) {
                try {
                    SendMinecraftCommand::run(
                        $account->whitelistRemoveCommand(),
                        'whitelist',
                        $account->username,
                        $parent
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to whitelist-remove MC account for parent disable', [
                        'username' => $account->username,
                        'error' => $e->getMessage(),
                    ]);
                }
                $account->status = MinecraftAccountStatus::ParentDisabled;
                $account->save();
            }
        } else {
            foreach ($child->minecraftAccounts()->where('status', MinecraftAccountStatus::ParentDisabled)->get() as $account) {
                try {
                    SendMinecraftCommand::run(
                        $account->whitelistAddCommand(),
                        'whitelist',
                        $account->username,
                        $parent
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to whitelist-add MC account for parent enable', [
                        'username' => $account->username,
                        'error' => $e->getMessage(),
                    ]);
                }
                $account->status = MinecraftAccountStatus::Active;
                $account->save();
            }

            try {
                SyncMinecraftRanks::run($child);
            } catch (\Exception $e) {
                Log::error('Failed to sync MC ranks after parent enable', [
                    'user_id' => $child->id,
                    'error' => $e->getMessage(),
                ]);
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
                } catch (\Exception $e) {
                    Log::warning('Failed to strip Discord roles for parent disable', [
                        'discord_user_id' => $discordAccount->discord_user_id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $discordAccount->status = DiscordAccountStatus::ParentDisabled;
                $discordAccount->save();
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
