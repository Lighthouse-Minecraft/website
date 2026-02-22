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
        return $user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Meeting Secretary');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MeetingNote $meetingNote): bool
    {
        return $user->isAtLeastRank(StaffRank::Officer) || $user->hasRole('Meeting Secretary');
    }

    public function updateSave(User $user, MeetingNote $meetingNote): bool
    {
        if ($meetingNote->locked_by == $user->id) {
            return true;
        }

        return false;
    }
}
