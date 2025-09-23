<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Page;
use App\Models\User;

class PagePolicy
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
        return $user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Page Editor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Page $page): bool
    {
        return $page->is_published || $user->hasRole('Page Editor') || $user->isAtLeastRank(StaffRank::CrewMember);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ($user->hasRole('Page Editor') || $user->isAtLeastRank(StaffRank::Officer)) && ($user->isInDepartment(StaffDepartment::Steward) || $user->isInDepartment(StaffDepartment::Engineer));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Page $page): bool
    {
        return ($user->hasRole('Page Editor') || $user->isAtLeastRank(StaffRank::Officer)) && ($user->isInDepartment(StaffDepartment::Steward) || $user->isInDepartment(StaffDepartment::Engineer));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Page $page): bool
    {
        // Only those who can create pages can delete them
        return $this->create($user) || ($user->isAtLeastRank(StaffRank::Officer) && ($user->isInDepartment(StaffDepartment::Command)));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Page $page): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Page $page): bool
    {
        return false;
    }
}
