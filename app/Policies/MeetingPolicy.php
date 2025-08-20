<?php

namespace App\Policies;

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer))) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAtLeastRank(StaffRank::CrewMember) || $user->hasRole('Meeting Secretary');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        return $user->isAtLeastRank(StaffRank::CrewMember) || $user->hasRole('Meeting Secretary');
    }

    public function attend(User $user, Meeting $meeting): bool
    {
        return $user->isAtLeastRank(StaffRank::CrewMember) || $user->hasRole('Meeting Secretary');
    }

    public function viewAnyPrivate(User $user): bool
    {
        return $user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Meeting Secretary');
    }

    public function viewAnyPublic(User $user): bool
    {
        return $user->isAtLeastLevel(MembershipLevel::Resident) || $user->hasRole('Meeting Secretary');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->isAtLeastRank(StaffRank::Officer)) {
            return true;
        }

        if ($user->hasRole('Meeting Secretary')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isAtLeastRank(StaffRank::Officer)) {
            return true;
        }

        if ($user->hasRole('Meeting Secretary')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        return false;
    }
}
