<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\User;

class CredentialPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Vault Manager') || $user->isAtLeastRank(\App\Enums\StaffRank::JrCrew);
    }

    public function view(User $user, Credential $credential): bool
    {
        if ($user->hasRole('Vault Manager')) {
            return true;
        }

        $positionId = $user->staffPosition?->id;
        if ($positionId === null) {
            return false;
        }

        return $credential->staffPositions()->where('staff_positions.id', $positionId)->exists();
    }

    public function update(User $user, Credential $credential): bool
    {
        return $this->view($user, $credential);
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->hasRole('Vault Manager');
    }

    public function managePositions(User $user, Credential $credential): bool
    {
        return $user->hasRole('Vault Manager');
    }
}
