<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffApplication;
use App\Models\User;

class StaffApplicationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, StaffApplication $application): bool
    {
        return $user->id === $application->user_id;
    }

    public function create(User $user): bool
    {
        return ! $user->in_brig;
    }

    public function update(User $user, StaffApplication $application): bool
    {
        return false;
    }

    public function delete(User $user, StaffApplication $application): bool
    {
        return false;
    }
}
