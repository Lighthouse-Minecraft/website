<?php

namespace App\Actions;

use App\Models\MinecraftAccount;
use App\Services\MinecraftRconService;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncMinecraftAccount
{
    use AsAction;

    /**
     * Compute and apply the desired Minecraft state for a single active account.
     *
     * Evaluates whitelist eligibility, member rank, and staff assignment from the
     * account owner's current website state — membership level, brig status, and
     * parent Minecraft restrictions — and sends the appropriate RCON commands to
     * realise that state. Stale whitelist or staff access is removed when the user
     * is no longer eligible.
     *
     * @return array{
     *     eligible: bool,
     *     whitelist: array{success: bool, action: string},
     *     rank: array{success: bool, rank: string}|null,
     *     staff: array{success: bool, action: string, department: string|null}|null,
     * }
     */
    public function handle(MinecraftAccount $account): array
    {
        $user = $account->user;
        $rcon = app(MinecraftRconService::class);

        $rank = $user->membership_level->minecraftRank();
        $eligible = $rank !== null && ! $user->isInBrig() && $user->parent_allows_minecraft;

        if (! $eligible) {
            $whitelistResult = $rcon->executeCommand(
                $account->whitelistRemoveCommand(),
                'whitelist',
                $account->username,
                $user,
                ['action' => 'sync_remove_ineligible']
            );

            return [
                'eligible' => false,
                'whitelist' => ['success' => $whitelistResult['success'], 'action' => 'remove'],
                'rank' => null,
                'staff' => null,
            ];
        }

        $staffPosition = $user->minecraftStaffPosition();

        $syncResult = $rcon->executeCommand(
            $account->syncUserCommand($rank, $staffPosition),
            'sync',
            $account->username,
            $user,
            ['action' => 'sync_user']
        );

        RecordActivity::run(
            $user,
            'minecraft_rank_synced',
            "Synced Minecraft rank to {$rank} for {$account->username}"
        );

        if ($staffPosition !== 'none') {
            RecordActivity::run(
                $user,
                'minecraft_staff_position_set',
                "Set Minecraft staff position to {$staffPosition} for {$account->username}"
            );

            $staffReturn = [
                'success' => $syncResult['success'],
                'action' => 'set',
                'department' => $staffPosition,
            ];
        } else {
            RecordActivity::run(
                $user,
                'minecraft_staff_position_removed',
                "Removed Minecraft staff position for {$account->username}"
            );

            $staffReturn = [
                'success' => $syncResult['success'],
                'action' => 'remove',
                'department' => null,
            ];
        }

        return [
            'eligible' => true,
            'whitelist' => ['success' => $syncResult['success'], 'action' => 'add'],
            'rank' => ['success' => $syncResult['success'], 'rank' => $rank],
            'staff' => $staffReturn,
        ];
    }
}
