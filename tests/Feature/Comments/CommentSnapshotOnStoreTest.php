<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;

use function Pest\Laravel\post;

it('stores snapshot title/content when creating comment', function () {
    $user = User::factory()->create();
    $blog = Blog::factory()->create(['title' => 'Blog T', 'content' => 'Blog C']);
    $announcement = Announcement::factory()->create(['title' => 'Ann T', 'content' => 'Ann C']);
    $this->actingAs($user);

    post(route('comments.store'), [
        'content' => 'c1',
        'commentable_id' => $blog->id,
        'commentable_type' => 'blog',
    ])->assertRedirect();

    post(route('comments.store'), [
        'content' => 'c2',
        'commentable_id' => $announcement->id,
        'commentable_type' => 'announcement',
    ])->assertRedirect();

    $c1 = Comment::where('content', 'c1')->first();
    $c2 = Comment::where('content', 'c2')->first();

    expect($c1->commentable_title)->toBe('Blog T');
    expect($c1->commentable_content)->toBe('Blog C');
    expect($c2->commentable_title)->toBe('Ann T');
    expect($c2->commentable_content)->toBe('Ann C');
})->done(assignee: 'ghostridr');
