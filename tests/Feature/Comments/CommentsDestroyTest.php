<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Comment;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;

it('shows confirm destroy page for a comment (ACP)', function () {
    $admin = loginAsAdmin();

    $blog = Blog::factory()->create();
    $comment = Comment::factory()->forBlog($blog)->withAuthor($admin)->create();

    $response = get(route('acp.comments.confirmDelete', ['id' => $comment->id]));

    $response->assertSuccessful();
    $response->assertSee('You are about to delete this comment');
})->done(assignee: 'ghostridr');

it('deletes a comment and returns the destroy confirmation view with status', function () {
    $admin = loginAsAdmin();

    $blog = Blog::factory()->create();
    $comment = Comment::factory()->forBlog($blog)->withAuthor($admin)->create();

    $response = delete(route('acp.comments.delete', ['id' => $comment->id]), ['from' => 'acp']);

    $response->assertSuccessful();
    $response->assertSee('Comment deleted successfully!');

    expect(Comment::whereKey($comment->id)->exists())->toBeFalse();
})->done(assignee: 'ghostridr');
