<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncDiscordPermissions
{
    use AsAction;

    public function handle(User $user): void
    {
        SyncDiscordRoles::run($user);
        SyncDiscordStaff::run($user, $user->staff_department);
    }
}
