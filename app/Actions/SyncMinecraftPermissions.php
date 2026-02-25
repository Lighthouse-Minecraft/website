<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncMinecraftPermissions
{
    use AsAction;

    /**
     * Sync all Minecraft permissions for a user — both membership rank and staff position.
     *
     * Calls SyncMinecraftRanks (which handles the server-access guard) and
     * SyncMinecraftStaff (passing the user's current department, or null to remove staff).
     */
    public function handle(User $user): void
    {
        SyncMinecraftRanks::run($user);
        SyncMinecraftStaff::run($user, $user->staff_department);
    }
}
