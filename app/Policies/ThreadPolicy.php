<?php

namespace App\Policies;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Thread;
use App\Models\User;

class ThreadPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Admin and Command Officers can do everything
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
     * @param User $user The user attempting to create the thread.
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
     * Determine whether the user can reply to a thread
     */
    public function reply(User $user, Thread $thread): bool
    {
        // User can reply if they can view the thread
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