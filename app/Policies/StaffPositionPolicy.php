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
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StaffPosition $position): bool
    {
        return false;
    }

    public function delete(User $user, StaffPosition $position): bool
    {
        return false;
    }

    public function assign(User $user, StaffPosition $position): bool
    {
        return false;
    }
}
