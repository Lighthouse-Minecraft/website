<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\CommunityQuestion;
use App\Models\User;

class CommunityQuestionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->isAtLeastRank(StaffRank::Officer) && $user->isInDepartment(StaffDepartment::Command)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view-community-stories');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-community-stories');
    }

    public function update(User $user, CommunityQuestion $question): bool
    {
        return $user->can('manage-community-stories');
    }

    public function delete(User $user, CommunityQuestion $question): bool
    {
        if (! $user->can('manage-community-stories')) {
            return false;
        }

        return $question->approvedResponses()->doesntExist();
    }
}
