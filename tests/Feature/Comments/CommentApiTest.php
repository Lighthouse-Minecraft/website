<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\User;

use function Pest\Laravel\get;

describe('Comment API', function () {
    it('lists comments on index for admins', function () {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->create(['content' => 'Hello from API index']);

        $this->actingAs($admin);
        $res = get(route('comments.index'));
        $res->assertOk();
        $res->assertSee(e('Hello from API index'));
    })->done(assignee: 'ghostridr');

    it('shows a single comment detail page for any authenticated user', function () {
        $comment = Comment::factory()->create(['content' => 'Single comment show']);
        $this->actingAs(User::factory()->create());

        $res = get(route('comments.show', ['id' => $comment->id]));
        $res->assertOk();
        $res->assertSee(e('Single comment show'));
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
