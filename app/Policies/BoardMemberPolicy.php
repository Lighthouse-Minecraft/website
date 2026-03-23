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
        return $user->hasRole('Site Config - Manager');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Site Config - Manager');
    }

    public function update(User $user, BoardMember $boardMember): bool
    {
        return $user->hasRole('Site Config - Manager');
    }

    public function delete(User $user, BoardMember $boardMember): bool
    {
        return $user->hasRole('Site Config - Manager');
    }
}
