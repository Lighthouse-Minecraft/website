<?php

namespace App\Policies;

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\PrayerCountry;
use App\Models\User;

class PrayerCountryPolicy
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
        return $user->isInDepartment(StaffDepartment::Chaplain) && $user->isAtLeastRank(StaffRank::Officer);
    }

    public function viewPrayer(User $user): bool
    {
        return $user->isAtLeastLevel(MembershipLevel::Stowaway);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isInDepartment(StaffDepartment::Chaplain) && $user->isAtLeastRank(StaffRank::Officer);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PrayerCountry $prayerCountry): bool
    {
        return $user->isInDepartment(StaffDepartment::Chaplain) && $user->isAtLeastRank(StaffRank::Officer);
    }
}
