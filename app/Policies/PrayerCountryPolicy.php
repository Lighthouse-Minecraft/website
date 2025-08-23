<?php

namespace App\Policies;

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

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PrayerCountry $prayerCountry): bool
    {
        return true;
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
