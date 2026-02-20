<?php

namespace App\Actions;

use App\Enums\StaffDepartment;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncMinecraftStaff
{
    use AsAction;

    /**
     * Sync the Minecraft staff position for all of a user's active accounts.
     *
     * Pass a StaffDepartment to set the staff position (`lh setstaff <id> <department>`).
     * Pass null to remove the staff position (`lh removestaff <id>`).
     */
    public function handle(User $user, ?StaffDepartment $department = null): void
    {
        $activeAccounts = $user->minecraftAccounts()->active()->get();

        if ($activeAccounts->isEmpty()) {
            return;
        }

        foreach ($activeAccounts as $account) {
            if ($department !== null) {
                SendMinecraftCommand::dispatch(
                    "lh setstaff {$account->command_id} {$department->value}",
                    'rank',
                    $account->command_id,
                    $user,
                    ['action' => 'set_staff_position', 'department' => $department->value]
                );

                RecordActivity::handle(
                    $user,
                    'minecraft_staff_position_set',
                    "Set Minecraft staff position to {$department->label()} for {$account->username}"
                );
            } else {
                SendMinecraftCommand::dispatch(
                    "lh removestaff {$account->command_id}",
                    'rank',
                    $account->command_id,
                    $user,
                    ['action' => 'remove_staff_position']
                );

                RecordActivity::handle(
                    $user,
                    'minecraft_staff_position_removed',
                    "Removed Minecraft staff position for {$account->username}"
                );
            }
        }
    }
}
