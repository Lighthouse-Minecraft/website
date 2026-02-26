<?php

namespace App\Actions;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;
use App\Services\DiscordApiService;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncDiscordStaff
{
    use AsAction;

    public function handle(User $user, ?StaffDepartment $department = null): void
    {
        $accounts = $user->discordAccounts()->active()->get();

        if ($accounts->isEmpty()) {
            return;
        }

        $discordApi = app(DiscordApiService::class);

        // Build the list of all managed staff role IDs (departments + ranks)
        $managedRoleIds = [];
        foreach (StaffDepartment::cases() as $dept) {
            $roleId = $dept->discordRoleId();
            if ($roleId) {
                $managedRoleIds[] = $roleId;
            }
        }
        foreach (StaffRank::cases() as $rank) {
            $roleId = $rank->discordRoleId();
            if ($roleId) {
                $managedRoleIds[] = $roleId;
            }
        }

        // Build the desired role IDs based on user's current staff position
        $desiredRoleIds = [];
        if ($department !== null) {
            $roleId = $department->discordRoleId();
            if ($roleId) {
                $desiredRoleIds[] = $roleId;
            }
        }
        $staffRank = $user->staff_rank;
        if ($staffRank !== null && $staffRank !== StaffRank::None) {
            $roleId = $staffRank->discordRoleId();
            if ($roleId) {
                $desiredRoleIds[] = $roleId;
            }
        }

        foreach ($accounts as $account) {
            $discordApi->syncManagedRoles(
                $account->discord_user_id,
                $managedRoleIds,
                $desiredRoleIds
            );
        }

        if ($department !== null) {
            RecordActivity::run(
                $user,
                'discord_staff_synced',
                "Synced Discord staff roles: {$department->label()}"
            );
        } else {
            RecordActivity::run(
                $user,
                'discord_staff_removed',
                'Removed Discord staff roles'
            );
        }
    }
}
