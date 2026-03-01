<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\ReportCategory;
use App\Models\User;

class ReportCategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer))) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAtLeastRank(StaffRank::Officer);
    }

    public function create(User $user): bool
    {
        return $user->isAtLeastRank(StaffRank::Officer);
    }

    public function update(User $user, ReportCategory $category): bool
    {
        return $user->isAtLeastRank(StaffRank::Officer);
    }

    public function delete(User $user, ReportCategory $category): bool
    {
        return false;
    }
}
