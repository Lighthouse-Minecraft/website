<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Taxonomy;
use App\Models\User;

class TaxonomyPolicy
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
        return $user->isAdmin()
            || $user->isInDepartment(StaffDepartment::Command);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Taxonomy $taxonomy): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->isInDepartment(StaffDepartment::Command);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Taxonomy $taxonomy): bool
    {
        return $user->isAdmin()
            || $user->isInDepartment(StaffDepartment::Command);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Taxonomy $taxonomy): bool
    {
        return $user->isAdmin()
            || $user->isInDepartment(StaffDepartment::Command);
    }
}
