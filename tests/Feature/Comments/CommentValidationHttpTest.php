<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

describe('Comment Validation (HTTP)', function () {
    it('requires content and valid parent on store (web)', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $res = post(route('comments.store'), []);
        $res->assertSessionHasErrors(['content', 'commentable_type', 'commentable_id']);
    })->done(assignee: 'ghostridr');

    it('requires content and valid parent on store (json)', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $json = postJson(route('comments.store'), []);
        $json->assertUnprocessable();
        $json->assertJsonValidationErrors(['content', 'commentable_type', 'commentable_id']);
    })->done(assignee: 'ghostridr');

    it('accepts valid store for blog', function () {
        $user = User::factory()->create();
        $blog = Blog::factory()->create();
        $this->actingAs($user);

        $res = post(route('comments.store'), [
            'content' => 'A helpful comment',
            'commentable_id' => $blog->id,
            'commentable_type' => 'blog',
        ]);
        $res->assertRedirect();
    })->done(assignee: 'ghostridr');

    it('accepts valid store for announcement', function () {
        $user = User::factory()->create();
        $ann = Announcement::factory()->create();
        $this->actingAs($user);

        $res = post(route('comments.store'), [
            'content' => 'A helpful comment 2',
            'commentable_id' => $ann->id,
            'commentable_type' => 'announcement',
        ]);
        $res->assertRedirect();
    })->done(assignee: 'ghostridr');

    it('allows update content only', function () {
        $user = User::factory()->create();
        $blog = Blog::factory()->create();
        $this->actingAs($user);

        $res = post(route('comments.store'), [
            'content' => 'Original content',
            'commentable_id' => $blog->id,
            'commentable_type' => 'blog',
        ]);
        $res->assertRedirect();

        $commentId = (int) str($res->headers->get('location'))->afterLast('/')->value();
        // Fallback: fetch last comment if location parsing differs
        if ($commentId <= 0) {
            $commentId = Comment::latest('id')->value('id');
        }

        $update = put(route('comments.update', ['id' => $commentId]), [
            'content' => 'Edited',
        ]);
        $update->assertRedirect();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
