<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\CommunityResponse;
use App\Models\User;

class CommunityResponsePolicy
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

    public function view(User $user, CommunityResponse $response): bool
    {
        return $response->isApproved() || $response->user_id === $user->id;
    }

    public function update(User $user, CommunityResponse $response): bool
    {
        return $response->isEditable() && $response->user_id === $user->id;
    }

    public function delete(User $user, CommunityResponse $response): bool
    {
        return $response->isEditable() && $response->user_id === $user->id;
    }
}
