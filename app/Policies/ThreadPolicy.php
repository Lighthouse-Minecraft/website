<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\Thread;
use App\Models\User;

class ThreadPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // For reply and createTopic, always defer to the specific policy method
        // so lock/draft checks are respected even for admins
        if (in_array($ability, ['reply', 'createTopic'])) {
            return null;
        }

        // Admin and Command Officers can do everything else
        if ($user->isAdmin() || ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer))) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view all tickets
     */
    public function viewAll(User $user): bool
    {
        // Command can view all (handled in before)
        return false;
    }

    /**
     * Determine whether the user can view their department's tickets
     */
    public function viewDepartment(User $user): bool
    {
        // Any staff member can view their department's tickets
        return $user->staff_department !== null && $user->isAtLeastRank(StaffRank::CrewMember);
    }

    /**
     * Determine whether the user can view flagged tickets from any department
     */
    public function viewFlagged(User $user): bool
    {
        // Quartermaster can view flagged tickets
        return $user->isInDepartment(StaffDepartment::Quartermaster) && $user->isAtLeastRank(StaffRank::CrewMember);
    }

    /**
     * Determine whether the user can view a specific thread
     */
    public function view(User $user, Thread $thread): bool
    {
        return $thread->isVisibleTo($user);
    }

    /**
     * Determine if the user is allowed to create a new thread.
     *
     * @param  User  $user  The user attempting to create the thread.
     * @return bool `true` if the user is allowed to create a thread, `false` otherwise.
     */
    public function create(User $user): bool
    {
        // Users in the brig cannot open new tickets
        return ! $user->in_brig;
    }

    /**
     * Determine whether the user can create admin-action tickets
     */
    public function createAsStaff(User $user): bool
    {
        // Staff can create admin-action tickets
        return $user->isAtLeastRank(StaffRank::CrewMember);
    }

    /**
     * Determine whether the user can create a topic on a discipline report.
     * Report must be published. User must be the subject, a parent of the subject, or staff.
     */
    public function createTopic(User $user, DisciplineReport $report): bool
    {
        if (! $report->isPublished()) {
            return false;
        }

        // Admin and Command Officers can create topics on any published report
        if ($user->isAdmin() || ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer))) {
            return true;
        }

        // Report subject can create topic
        if ($user->id === $report->subject_user_id) {
            return true;
        }

        // Parent of report subject can create topic
        if ($user->children()->where('child_user_id', $report->subject_user_id)->exists()) {
            return true;
        }

        // Staff (JrCrew+) can create topic
        return $user->isAtLeastRank(StaffRank::JrCrew);
    }

    /**
     * Determine whether the user can add participants to a thread.
     */
    public function addParticipant(User $user, Thread $thread): bool
    {
        if (! $user->isAtLeastRank(StaffRank::CrewMember)) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can reply to a thread
     */
    public function reply(User $user, Thread $thread): bool
    {
        if ($thread->is_locked) {
            return false;
        }

        // Admin and Command Officers can reply to any unlocked thread
        if ($user->isAdmin() || ($user->isInDepartment(StaffDepartment::Command) && $user->isAtLeastRank(StaffRank::Officer))) {
            return true;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can add internal notes
     */
    public function internalNotes(User $user, Thread $thread): bool
    {
        // Staff who can view the thread can add internal notes
        if (! $user->isAtLeastRank(StaffRank::CrewMember)) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can change the thread status
     */
    public function changeStatus(User $user, Thread $thread): bool
    {
        // Staff who can view the thread can change status
        if (! $user->isAtLeastRank(StaffRank::CrewMember)) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can assign the thread
     */
    public function assign(User $user, Thread $thread): bool
    {
        // Officers and above who can view the thread can assign
        if (! $user->isAtLeastRank(StaffRank::Officer)) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can reroute (change department) of a thread
     */
    public function reroute(User $user, Thread $thread): bool
    {
        // Officers and above who can view the thread can reroute
        if (! $user->isAtLeastRank(StaffRank::Officer)) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can close a thread
     */
    public function close(User $user, Thread $thread): bool
    {
        // Staff who can view the thread can close it
        if ($user->isAtLeastRank(StaffRank::CrewMember) && $thread->isVisibleTo($user)) {
            return true;
        }

        // Non-staff users can close their own support tickets
        return $thread->created_by_user_id === $user->id;
    }
}
