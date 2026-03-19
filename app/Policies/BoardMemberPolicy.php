<?php

namespace App\Policies;

use App\Models\BoardMember;
use App\Models\User;

class BoardMemberPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Manage Site Config');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Manage Site Config');
    }

    public function update(User $user, BoardMember $boardMember): bool
    {
        return $user->hasRole('Manage Site Config');
    }

    public function delete(User $user, BoardMember $boardMember): bool
    {
        return $user->hasRole('Manage Site Config');
    }
}
