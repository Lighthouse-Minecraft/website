<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PagePolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin() || ($user->isInCommandDepartment() && $user->isOfficer())) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ($user->isOfficer() || $user->hasRole('Page Editor'));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Page $page): bool
    {
        return ($page->is_published || $user->hasRole('Page Editor') || $user->isOfficer() || $user->isCrewMember());
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ($user->isOfficer() && ($user->isInStewardDepartment() || $user->isInEngineeringDepartment()));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Page $page): bool
    {
        return ($user->hasRole('Page Editor') || ($user->isOfficer() && ($user->isInStewardDepartment() || $user->isInEngineeringDepartment())));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Page $page): bool
    {
        // Only those who can create pages can delete them
        return $this->create($user);
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
