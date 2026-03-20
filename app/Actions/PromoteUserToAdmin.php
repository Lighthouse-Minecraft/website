<?php

namespace App\Actions;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class PromoteUserToAdmin
{
    use AsAction;

    public function handle(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $user->admin_granted_at = now();
        $user->promoted_at = now();
        $user->save();

        RecordActivity::run($user, 'user_promoted_to_admin', 'Promoted to Admin role.');

        return true;
    }
}
