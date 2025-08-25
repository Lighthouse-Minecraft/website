<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Model', function () {
    it('normalizes commentable_type accessor to alias values', function () {
        $c1 = Comment::factory()->forBlog()->create();
        $c2 = Comment::factory()->forAnnouncement()->create();

        expect($c1->commentable_type)->toBe('blog');
        expect($c2->commentable_type)->toBe('announcement');
    })->done(assignee: 'ghostridr');

    it('sets commentable_type using class names to alias via mutator', function () {
        $c = Comment::factory()->create([
            'commentable_id' => Blog::factory(),
            'commentable_type' => Blog::class,
        ]);
        expect($c->commentable_type)->toBe('blog');

        $c->commentable_type = Announcement::class;
        $c->save();
        expect($c->fresh()->commentable_type)->toBe('announcement');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
