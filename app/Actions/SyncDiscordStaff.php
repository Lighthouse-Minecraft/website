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
        $staffRank = $user->staff_rank;

        foreach ($accounts as $account) {
            $discordUserId = $account->discord_user_id;

            // Remove all staff department roles
            foreach (StaffDepartment::cases() as $dept) {
                $roleId = $dept->discordRoleId();
                if ($roleId) {
                    $discordApi->removeRole($discordUserId, $roleId);
                }
            }

            // Remove all staff rank roles
            foreach (StaffRank::cases() as $rank) {
                $roleId = $rank->discordRoleId();
                if ($roleId) {
                    $discordApi->removeRole($discordUserId, $roleId);
                }
            }

            // Add the correct department role
            if ($department !== null) {
                $roleId = $department->discordRoleId();
                if ($roleId) {
                    $discordApi->addRole($discordUserId, $roleId);
                }
            }

            // Add the correct staff rank role
            if ($staffRank !== null && $staffRank !== StaffRank::None) {
                $roleId = $staffRank->discordRoleId();
                if ($roleId) {
                    $discordApi->addRole($discordUserId, $roleId);
                }
            }
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
