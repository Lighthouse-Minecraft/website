<?php

namespace App\Policies;

use App\Enums\StaffRank;
use App\Models\MeetingNote;
use App\Models\User;

class MeetingNotePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Meeting - Manager')
            || $user->isAtLeastRank(StaffRank::Officer);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MeetingNote $meetingNote): bool
    {
        if ($user->hasRole('Meeting - Manager')) {
            return true;
        }

        // Officers can edit their own department's notes
        if ($user->isAtLeastRank(StaffRank::Officer) && $user->staff_department !== null) {
            return $meetingNote->section_key === $user->staff_department->value;
        }

        return false;
    }

    public function updateSave(User $user, MeetingNote $meetingNote): bool
    {
        if ($meetingNote->locked_by == $user->id) {
            return true;
        }

        return false;
    }
}
