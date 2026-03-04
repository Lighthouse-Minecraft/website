<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use App\Models\User;

class StaffPositionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer))) {
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
