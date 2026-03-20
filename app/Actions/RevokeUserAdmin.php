<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RevokeUserAdmin
{
    use AsAction;

    public function handle(User $user): bool
    {
        if (! $user->isAdmin()) {
            return true;
        }

        $user->admin_granted_at = null;
        $user->save();

        RecordActivity::run($user, 'user_admin_revoked', 'Admin role revoked.');

        return true;
    }
}
