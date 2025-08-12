<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\MeetingNote;
use App\Models\User;

class MeetingNotePolicy
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
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MeetingNote $meetingNote): bool
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
    public function update(User $user, MeetingNote $meetingNote): bool
    {
        if ($user->isAtLeastRank(StaffRank::CrewMember)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MeetingNote $meetingNote): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MeetingNote $meetingNote): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MeetingNote $meetingNote): bool
    {
        return false;
    }
}
