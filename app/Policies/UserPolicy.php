<?php

namespace App\Policies;

use App\Enums\StaffRank;
use App\Models\User;

class UserPolicy
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
        return $user->hasRole('User - Manager');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function viewActivityLog(User $user, User $model): bool
    {
        return $user->hasRole('User - Manager');
    }

    public function viewPii(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole('PII - Viewer');
    }

    public function viewStaffPhone(User $user, User $model): bool
    {
        $actorAllowed = $user->isAtLeastRank(StaffRank::Officer) || $user->is_board_member;
        $targetIsStaff = $model->isAtLeastRank(StaffRank::JrCrew) || $model->is_board_member;

        return $actorAllowed && $targetIsStaff;
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
        return $user->hasRole('User - Manager') || $user->id == $model->id;
    }

    public function updateStaffPosition(User $user, User $model): bool
    {
        return false;
    }

    public function removeStaffPosition(User $user, User $model): bool
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
