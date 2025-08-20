<?php

use App\Actions\AcknowledgeBlog;
use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('AcknowledgeBlog Action', function () {
    it('acknowledges a published blog for a user', function () {
        $user = User::factory()->create();
        $blog = Blog::factory()->published()->create();

        app(AcknowledgeBlog::class)->run($blog, $user);

        $this->assertDatabaseHas('blog_author', [
            'author_id' => $user->id,
            'blog_id' => $blog->id,
        ]);
    })->done(assignee: 'ghostridr');

    it('is idempotent when acknowledging the same blog twice', function () {
        $user = User::factory()->create();
        $blog = Blog::factory()->published()->create();

        $action = app(AcknowledgeBlog::class);

        $action->run($blog, $user);
        $action->run($blog, $user);

        $count = DB::table('blog_author')
            ->where('author_id', $user->id)
            ->where('blog_id', $blog->id)
            ->count();

        expect($count)->toBe(1);
    })->done(assignee: 'ghostridr');

    it('acknowledges a blog for the authenticated user if no user is passed', function () {
        $blog = Blog::factory()->published()->create();
        $user = User::factory()->create();
        loginAs($user);

        app(AcknowledgeBlog::class)->run($blog, null);

        $this->assertDatabaseHas('blog_author', [
            'author_id' => $user->id,
            'blog_id' => $blog->id,
        ]);
    })->done(assignee: 'ghostridr');

    it('throws an exception if the user is not authenticated', function () {
        $blog = Blog::factory()->published()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User must be authenticated to acknowledge a blog.');

        app(AcknowledgeBlog::class)->run($blog, null);
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
