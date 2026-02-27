<?php

namespace App\Policies;

use App\Enums\MinecraftAccountStatus;
use App\Models\MinecraftAccount;
use App\Models\User;

class MinecraftAccountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MinecraftAccount $minecraftAccount): bool
    {
        return false;
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
    public function update(User $user, MinecraftAccount $minecraftAccount): bool
    {
        return false;
    }

    /**
     * Determine whether the user can set the account as their primary.
     */
    public function setPrimary(User $user, MinecraftAccount $minecraftAccount): bool
    {
        return $minecraftAccount->status === MinecraftAccountStatus::Active
            && ($user->id === $minecraftAccount->user_id || $user->isAdmin());
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MinecraftAccount $minecraftAccount): bool
    {
        // Users can delete their own accounts, admins can delete any
        return $user->id === $minecraftAccount->user_id || $user->isAdmin();
    }

    /**
     * Determine whether the user can reactivate a removed account.
     */
    public function reactivate(User $user, MinecraftAccount $minecraftAccount): bool
    {
        return $minecraftAccount->status === MinecraftAccountStatus::Removed
            && ($user->id === $minecraftAccount->user_id || $user->isAdmin());
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MinecraftAccount $minecraftAccount): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MinecraftAccount $minecraftAccount): bool
    {
        return $minecraftAccount->status === MinecraftAccountStatus::Removed
            && $user->isAdmin();
    }
}
