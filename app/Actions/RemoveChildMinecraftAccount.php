<?php

namespace App\Actions;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveChildMinecraftAccount
{
    use AsAction;

    /**
     * Remove a child's Minecraft account (parent action).
     *
     * @return array{success: bool, message: string}
     */
    public function handle(User $parent, int $accountId): array
    {
        $account = MinecraftAccount::findOrFail($accountId);
        $child = $account->user;

        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            return ['success' => false, 'message' => 'You do not have permission to manage this account.'];
        }

        if ($account->status !== MinecraftAccountStatus::Active) {
            return ['success' => false, 'message' => 'This account cannot be removed in its current state.'];
        }

        $rconService = app(MinecraftRconService::class);

        $rconService->executeCommand(
            "lh setmember {$account->username} default",
            'rank', $account->username, $parent,
            ['action' => 'parent_remove_rank_reset', 'affected_user_id' => $child->id]
        );

        $whitelistResult = $rconService->executeCommand(
            $account->whitelistRemoveCommand(),
            'whitelist', $account->username, $parent,
            ['action' => 'parent_remove', 'affected_user_id' => $child->id]
        );

        if (! $whitelistResult['success']) {
            return ['success' => false, 'message' => 'Failed to remove from whitelist. Account not removed.'];
        }

        $account->status = MinecraftAccountStatus::Removed;
        $account->save();

        if ($account->is_primary) {
            $account->update(['is_primary' => false]);
            AutoAssignPrimaryAccount::run($child);
        }

        RecordActivity::run(
            $child,
            'minecraft_account_removed_by_parent',
            "{$parent->name} removed {$account->account_type->label()} account: {$account->username}"
        );

        return ['success' => true, 'message' => "Minecraft account {$account->username} has been removed."];
    }
}
