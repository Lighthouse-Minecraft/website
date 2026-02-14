<?php

namespace App\Policies;

use App\Enums\MessageKind;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Message;
use App\Models\User;

class MessagePolicy
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
     * Determine whether the user can view the message
     */
    public function view(User $user, Message $message): bool
    {
        // Can't view internal notes unless staff
        if ($message->kind === MessageKind::InternalNote && ! $user->isAtLeastRank(StaffRank::CrewMember)) {
            return false;
        }

        // Can view if they can view the thread
        return $message->thread->isVisibleTo($user);
    }

    /**
     * Determine whether the user can flag a message
     */
    public function flag(User $user, Message $message): bool
    {
        // Users can flag messages in threads they participate in
        // Staff cannot flag their own messages or system messages
        if ($message->kind === MessageKind::System) {
            return false;
        }

        if ($message->user_id === $user->id) {
            return false;
        }

        return $message->thread->participants()->where('user_id', $user->id)->exists();
    }
}
