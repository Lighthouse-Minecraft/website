<?php

namespace App\Policies;

use App\Enums\StaffRank;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Admins/Officers are allowed broadly EXCEPT editing (update) others' comments.
        if ($ability === 'update') {
            return null; // defer to the update() rule below
        }

        if ($user->isAdmin() || $user->isAtLeastRank(StaffRank::Officer)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any comments.
     */
    public function viewAny(User $user): bool
    {
        // Only Admins or Officers may view the comments index
        return $user->isAdmin() || $user->isAtLeastRank(StaffRank::Officer);
    }

    /**
     * Determine whether the user can create comments.
     */
    public function create(User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view a comment.
     */
    public function view(User $user, Comment $comment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update a comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        // Only the author can edit their own comment
        return $user->id === $comment->author_id;
    }

    /**
     * Determine whether the user can review/moderate a comment (approve/reject).
     */
    public function review(User $user, Comment $comment): bool
    {
        // Admins or Officers can review/moderate
        return $user->isAdmin() || $user->isAtLeastRank(StaffRank::Officer);
    }

    /**
     * Determine whether the user can delete a comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->author_id
            || $user->isAdmin()
            || $user->isAtLeastRank(StaffRank::Officer);
    }
}
