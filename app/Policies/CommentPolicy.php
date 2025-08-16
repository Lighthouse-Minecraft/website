<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can view any comments.
     */
    public function viewAny(User $user)
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can create comments.
     */
    public function create(User $user)
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view a comment.
     */
    public function view(User $user, Comment $comment)
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can update a comment.
     */
    public function update(User $user, Comment $comment)
    {
        return $user->id === $comment->author_id;
    }

    /**
     * Determine whether the user can delete a comment.
     */
    public function delete(User $user, Comment $comment)
    {
        return $user->id === $comment->author_id || $user->is_admin;
    }
}
