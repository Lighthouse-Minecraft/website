<?php

namespace App\Policies;

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

        // Admin can do everything else
        if ($user->isAdmin()) {
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
        return $user->staff_department !== null
            && ($user->hasRole('Ticket - User') || $user->hasRole('Ticket - Manager'));
    }

    /**
     * Determine whether the user can view flagged tickets from any department
     */
    public function viewFlagged(User $user): bool
    {
        return $user->hasRole('Moderator') || $user->hasRole('Ticket - Manager');
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
        return $user->hasRole('Ticket - User');
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

        // Admin can create topics on any published report
        if ($user->isAdmin()) {
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

        // Staff with Ticket - User role can create topic
        return $user->hasRole('Ticket - User');
    }

    /**
     * Determine whether the user can add participants to a thread.
     */
    public function addParticipant(User $user, Thread $thread): bool
    {
        if (! $user->hasRole('Ticket - User')) {
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

        // Admin can reply to any unlocked thread
        if ($user->isAdmin()) {
            return true;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can add internal notes
     */
    public function internalNotes(User $user, Thread $thread): bool
    {
        if (! $user->hasRole('Internal Note - Manager')) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can change the thread status
     */
    public function changeStatus(User $user, Thread $thread): bool
    {
        if (! $user->hasRole('Ticket - User')) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can assign the thread
     */
    public function assign(User $user, Thread $thread): bool
    {
        if (! $user->hasRole('Ticket - Manager')) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can reroute (change department) of a thread
     */
    public function reroute(User $user, Thread $thread): bool
    {
        if (! $user->hasRole('Ticket - Manager')) {
            return false;
        }

        return $thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can close a thread
     */
    public function close(User $user, Thread $thread): bool
    {
        // Staff with Ticket - User role who can view the thread can close it
        if ($user->hasRole('Ticket - User') && $thread->isVisibleTo($user)) {
            return true;
        }

        // Non-staff users can close their own support tickets
        return $thread->created_by_user_id === $user->id;
    }
}
