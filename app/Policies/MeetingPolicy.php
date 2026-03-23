<?php

namespace App\Policies;

use App\Enums\MembershipLevel;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Staff Access');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('Staff Access');
    }

    public function attend(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('Staff Access');
    }

    public function viewAnyPrivate(User $user): bool
    {
        return $user->hasRole('Staff Access');
    }

    public function viewAnyPublic(User $user): bool
    {
        return $user->isAtLeastLevel(MembershipLevel::Resident);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Meeting - Manager');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('Meeting - Manager');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        return false;
    }
}
