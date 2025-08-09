<?php

namespace App\Policies;

use App\Enums\{MembershipLevel, StaffDepartment, StaffRank};
use App\Models\{Announcement, User};
use Illuminate\Auth\Access\{Response};

class AnnouncementPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()
            || ($user->isInDepartment(StaffDepartment::Command)
                && $user->isAtLeastRank(StaffRank::Officer)
                )
            ) {

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
        return (
            $user->hasRole('Announcement Editor')
            || $user->isAtLeastRank(StaffRank::Officer)
            || ($user->isAtLeastRank(StaffRank::CrewMember)
                && ($user->isInDepartment(StaffDepartment::Engineer)
                    || $user->isInDepartment(StaffDepartment::Steward)
                )
            )
        );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        return (
            $user->hasRole('Announcement Editor')
            || $user->isAtLeastRank(StaffRank::Officer)
            || ($user->isAtLeastRank(StaffRank::CrewMember)
                && ($user->isInDepartment(StaffDepartment::Engineer)
                    || $user->isInDepartment(StaffDepartment::Steward)
                )
            )
        );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        return (
            $user->hasRole('Announcement Editor')
            || ($user->isAtLeastRank(StaffRank::Officer)
                && ($user->isInDepartment(StaffDepartment::Engineer)
                    || $user->isInDepartment(StaffDepartment::Steward)
                )
            )
        );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Announcement $announcement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Announcement $announcement): bool
    {
        return false;
    }
}
