<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class ReactivateMinecraftAccount
{
    use AsAction;

    /**
     * Reactivate a Removed Minecraft account back to Active status.
     *
     * Re-adds the player to the whitelist and re-syncs their rank and staff position.
     * Checks that the account owner has not reached the max account limit before reactivating.
     */
    public function handle(MinecraftAccount $account, User $user): array
    {
        if ($account->status !== MinecraftAccountStatus::Removed) {
            return [
                'success' => false,
                'message' => 'Only removed accounts can be reactivated.',
            ];
        }

        $owner = $account->user;
        $maxAccounts = config('lighthouse.max_minecraft_accounts');
        $currentCount = $owner->minecraftAccounts()->countingTowardLimit()->count();

        if ($currentCount >= $maxAccounts) {
            return [
                'success' => false,
                'message' => "Account limit reached ({$maxAccounts}). Remove another account before reactivating.",
            ];
        }

        if ($owner->isInBrig()) {
            return [
                'success' => false,
                'message' => 'Cannot reactivate accounts while in the brig.',
            ];
        }

        $rconService = app(MinecraftRconService::class);

        $whitelistResult = $rconService->executeCommand(
            $account->whitelistAddCommand(),
            'whitelist',
            $account->username,
            $user,
            ['action' => 'reactivate']
        );

        if (! $whitelistResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to add player to server whitelist. Please try again later.',
            ];
        }

        $account->status = MinecraftAccountStatus::Active;
        $account->save();

        SyncMinecraftRanks::run($owner);

        if ($owner->staff_department !== null) {
            SyncMinecraftStaff::run($owner, $owner->staff_department);
        }

        RecordActivity::handle(
            $owner,
            'minecraft_account_reactivated',
            "Reactivated {$account->account_type->label()} account: {$account->username}"
        );

        return [
            'success' => true,
            'message' => 'Minecraft account reactivated successfully.',
        ];
    }
}
