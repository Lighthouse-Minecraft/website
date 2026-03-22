<?php

namespace App\Policies;

use App\Models\StaffApplication;
use App\Models\User;

class StaffApplicationPolicy
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
        return $user->hasRole('Applicant Review - All') || $user->hasRole('Applicant Review - Department');
    }

    public function view(User $user, StaffApplication $application): bool
    {
        return $user->id === $application->user_id;
    }

    public function create(User $user): bool
    {
        return ! $user->in_brig;
    }

    public function update(User $user, StaffApplication $application): bool
    {
        return false;
    }

    public function delete(User $user, StaffApplication $application): bool
    {
        return false;
    }
}
