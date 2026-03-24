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

        $whitelistResult = $rcon->executeCommand(
            $account->whitelistAddCommand(),
            'whitelist',
            $account->username,
            $user,
            ['action' => 'sync_add_eligible']
        );

        $rankResult = $rcon->executeCommand(
            "lh setmember {$account->username} {$rank}",
            'rank',
            $account->username,
            $user,
            ['action' => 'sync_rank', 'membership_level' => $user->membership_level->value]
        );

        RecordActivity::handle(
            $user,
            'minecraft_rank_synced',
            "Synced Minecraft rank to {$rank} for {$account->username}"
        );

        $staffDepartment = $user->staff_department;

        if ($staffDepartment !== null) {
            $staffResult = $rcon->executeCommand(
                "lh setstaff {$account->username} {$staffDepartment->value}",
                'staff',
                $account->username,
                $user,
                ['action' => 'set_staff_position', 'department' => $staffDepartment->value]
            );

            RecordActivity::handle(
                $user,
                'minecraft_staff_position_set',
                "Set Minecraft staff position to {$staffDepartment->label()} for {$account->username}"
            );

            $staffReturn = [
                'success' => $staffResult['success'],
                'action' => 'set',
                'department' => $staffDepartment->value,
            ];
        } else {
            $staffResult = $rcon->executeCommand(
                "lh removestaff {$account->username}",
                'staff',
                $account->username,
                $user,
                ['action' => 'remove_staff_position']
            );

            RecordActivity::handle(
                $user,
                'minecraft_staff_position_removed',
                "Removed Minecraft staff position for {$account->username}"
            );

            $staffReturn = [
                'success' => $staffResult['success'],
                'action' => 'remove',
                'department' => null,
            ];
        }

        return [
            'eligible' => true,
            'whitelist' => ['success' => $whitelistResult['success'], 'action' => 'add'],
            'rank' => ['success' => $rankResult['success'], 'rank' => $rank],
            'staff' => $staffReturn,
        ];
    }
}
