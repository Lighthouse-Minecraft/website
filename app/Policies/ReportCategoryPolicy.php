<?php

namespace App\Policies;

use App\Models\ReportCategory;
use App\Models\User;

class ReportCategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return null;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Site Config - Manager');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Site Config - Manager');
    }

    public function update(User $user, ReportCategory $category): bool
    {
        return $user->hasRole('Site Config - Manager');
    }

    public function delete(User $user, ReportCategory $category): bool
    {
        return false;
    }
}
