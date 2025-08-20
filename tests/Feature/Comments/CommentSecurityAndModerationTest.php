<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;

use function Pest\Laravel\post;
use function Pest\Laravel\put;

describe('Comment Security & Moderation', function () {
    it('prevents non-author from updating a comment', function () {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $blog = Blog::factory()->create();
        $comment = Comment::factory()->forBlog($blog)->withAuthor($author)->create();

        $this->actingAs($other);
        $res = put(route('comments.update', ['id' => $comment->id]), ['content' => 'Hacked']);
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('allows author to update their comment', function () {
        $author = User::factory()->create();
        $blog = Blog::factory()->create();
        $comment = Comment::factory()->forBlog($blog)->withAuthor($author)->create();

        $this->actingAs($author);
        $res = put(route('comments.update', ['id' => $comment->id]), ['content' => 'Updated by author']);
        $res->assertRedirect();
    })->done(assignee: 'ghostridr');

    it('allows admins/officers to approve or reject', function () {
        $admin = User::factory()->admin()->create();
        $blog = Blog::factory()->create();
        $comment = Comment::factory()->forBlog($blog)->withAuthor()->create(['needs_review' => true]);

        $this->actingAs($admin);
        $approve = post(route('acp.comments.approve', ['id' => $comment->id]));
        $approve->assertRedirect();

        $comment->refresh();
        expect($comment->status)->toBe('approved');
        expect((bool) $comment->needs_review)->toBeFalse();

        // Create another pending comment to test reject
        $comment2 = Comment::factory()->forBlog($blog)->withAuthor()->create(['needs_review' => true]);
        $reject = post(route('acp.comments.reject', ['id' => $comment2->id]));
        $reject->assertRedirect();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
