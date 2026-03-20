<?php

namespace App\Policies;

use App\Models\StaffPosition;
use App\Models\User;

class StaffPositionPolicy
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

    public function update(User $user, StaffPosition $position): bool
    {
        return $user->hasRole('Manage Site Config');
    }

    public function delete(User $user, StaffPosition $position): bool
    {
        return $user->hasRole('Manage Site Config');
    }

    public function manageRoles(User $user, StaffPosition $position): bool
    {
        // Users cannot manage roles on their own position (to prevent self-escalation)
        if ($user->staffPosition && $user->staffPosition->id === $position->id) {
            return false;
        }

        return $user->hasRole('Manage Site Config');
    }

    public function assign(User $user, StaffPosition $position): bool
    {
        return $user->hasRole('Manage Site Config');
    }
}
