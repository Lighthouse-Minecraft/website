<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class PromoteUserToAdmin
{
    use AsAction;

    public function handle(User $user)
    {

        if ($user->roles()->where('name', 'Admin')->exists()) {
            // User is already an Admin
            return true;
        }

        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        if (! $adminRole) {

            return false;
        }

        $user->roles()->attach($adminRole->id);
        $user->promoted_at = now();
        $user->save();

        RecordActivity::run($user, 'user_promoted_to_admin', 'Promoted to Admin role.');

        return true;
    }
}
