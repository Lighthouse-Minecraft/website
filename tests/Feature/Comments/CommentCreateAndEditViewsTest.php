<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;

use function Pest\Laravel\get;

describe('Comment Create/Edit Views', function () {
    it('shows create view for admins', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $res = get(route('acp.comments.create'));
        $res->assertOk();
        $res->assertSee('Create Comment');
    })->done(assignee: 'ghostridr');

    it('shows edit view for author', function () {
        $author = User::factory()->create();
        $blog = Blog::factory()->create();
        $comment = Comment::factory()->forBlog($blog)->withAuthor($author)->create();

        $this->actingAs($author);
        $res = get(route('acp.comments.edit', ['id' => $comment->id]));
        $res->assertOk();
        $res->assertSee('Edit Comment');
        $res->assertSee('Attached To');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
