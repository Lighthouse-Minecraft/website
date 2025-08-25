<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;

it('allows the author to delete their own comment', function () {
    $author = User::factory()->create();
    $blog = Blog::factory()->create();
    $comment = Comment::factory()->forBlog($blog)->withAuthor($author)->create();

    $this->actingAs($author)
        ->delete(route('comments.destroy', $comment->id))
        ->assertSuccessful();

    expect(Comment::find($comment->id))->toBeNull();
})->done(assignee: 'ghostridr');
