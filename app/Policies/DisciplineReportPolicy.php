<?php

namespace App\Policies;

use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\User;

class DisciplineReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['delete', 'publish'])) {
            return null;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Manage Discipline Reports') || $user->isAtLeastRank(StaffRank::JrCrew);
    }

    public function view(User $user, DisciplineReport $report): bool
    {
        if ($user->hasRole('Manage Discipline Reports')) {
            return true;
        }

        if ($user->isAtLeastRank(StaffRank::JrCrew)) {
            return true;
        }

        if ($user->id === $report->subject_user_id && $report->isPublished()) {
            return true;
        }

        if ($report->isPublished() && $user->children()->where('child_user_id', $report->subject_user_id)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Manage Discipline Reports') || $user->isAtLeastRank(StaffRank::JrCrew);
    }

    public function update(User $user, DisciplineReport $report): bool
    {
        if (! $report->isDraft()) {
            return false;
        }

        if ($user->hasRole('Manage Discipline Reports')) {
            return true;
        }

        return $user->id === $report->reporter_user_id
            || $user->isAtLeastRank(StaffRank::Officer);
    }

    public function publish(User $user, DisciplineReport $report): bool
    {
        if (! $report->isDraft()) {
            return false;
        }

        if (! $user->hasRole('Publish Discipline Reports')) {
            return false;
        }

        // When the subject is a staff member, the reporter cannot publish their own report
        if ($report->subject?->staff_rank && $report->subject->staff_rank !== StaffRank::None
            && $user->id === $report->reporter_user_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, DisciplineReport $report): bool
    {
        return false;
    }
}
