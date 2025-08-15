<?php

namespace App\Policies;

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (
            $user->isAdmin() || ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer))
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
        return $user->isAdmin() || ($user->isInDepartment(StaffDepartment::Quartermaster) && $user->isAtLeastRank(StaffRank::Officer));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id == $model->id || $user->isAtLeastLevel(MembershipLevel::Traveler);
    }

    public function viewActivityLog(User $user, User $model): bool
    {
        return $user->id == $model->id || $user->isInDepartment(StaffDepartment::Quartermaster) || $user->isAtLeastRank(StaffRank::Officer);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return ($user->isInDepartment(StaffDepartment::Quartermaster) && $user->isAtLeastRank(StaffRank::Officer)) || $user->id == $model->id;
    }

    public function updateStaffPosition(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
