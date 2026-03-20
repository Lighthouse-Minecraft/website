<?php

namespace App\Policies;

use App\Models\CommunityResponse;
use App\Models\User;

class CommunityResponsePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, CommunityResponse $response): bool
    {
        if ($user->hasRole('Manage Community Stories')) {
            return true;
        }

        return $response->isApproved() || $response->user_id === $user->id;
    }

    public function update(User $user, CommunityResponse $response): bool
    {
        if ($user->hasRole('Manage Community Stories')) {
            return true;
        }

        return $response->isEditable() && $response->user_id === $user->id;
    }

    public function delete(User $user, CommunityResponse $response): bool
    {
        if ($user->hasRole('Manage Community Stories')) {
            return true;
        }

        return $response->isEditable() && $response->user_id === $user->id;
    }
}
