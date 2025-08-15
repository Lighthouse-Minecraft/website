<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isInDepartment(StaffDepartment::Command)
            && $user->isAtLeastRank(StaffRank::Officer)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Announcement $announcement): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return
            $user->hasRole('Announcement Editor')
            || $user->isAtLeastRank(StaffRank::Officer)
            || ($user->isAtLeastRank(StaffRank::CrewMember)
                && ($user->isInDepartment(StaffDepartment::Engineer)
                    || $user->isInDepartment(StaffDepartment::Steward)
                )
            );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        return
            $user->hasRole('Announcement Editor')
            || $user->isAtLeastRank(StaffRank::Officer)
            || ($user->isAtLeastRank(StaffRank::CrewMember)
                && ($user->isInDepartment(StaffDepartment::Engineer)
                    || $user->isInDepartment(StaffDepartment::Steward)
                )
            );
    }

    public function acknowledge(User $user, Announcement $announcement): bool
    {
        return $user != null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        return
            $user->hasRole('Announcement Editor')
            || ($user->isAtLeastRank(StaffRank::Officer)
                && ($user->isInDepartment(StaffDepartment::Engineer)
                    || $user->isInDepartment(StaffDepartment::Steward)
                )
            );
    }
}
