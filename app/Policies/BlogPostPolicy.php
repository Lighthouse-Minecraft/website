<?php

namespace App\Policies;

use App\Enums\StaffRank;
use App\Models\BlogPost;
use App\Models\User;

class BlogPostPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability !== 'delete' && $user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Blog - Author');
    }

    public function update(User $user, BlogPost $post): bool
    {
        return $user->hasRole('Blog - Author');
    }

    public function submitForReview(User $user, BlogPost $post): bool
    {
        return $user->hasRole('Blog - Author') && $post->author_id === $user->id && $post->isDraft();
    }

    public function approve(User $user, BlogPost $post): bool
    {
        return $user->hasRole('Blog - Author')
            && $post->author_id !== $user->id
            && $post->status === \App\Enums\BlogPostStatus::InReview;
    }

    public function archive(User $user, BlogPost $post): bool
    {
        return $user->hasRole('Blog - Author') && $post->isPublished();
    }

    public function delete(User $user, BlogPost $post): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isAtLeastRank(StaffRank::Officer) && $user->staff_department === \App\Enums\StaffDepartment::Command) {
            return true;
        }

        return $user->hasRole('Blog - Author') && $post->author_id === $user->id;
    }
}
