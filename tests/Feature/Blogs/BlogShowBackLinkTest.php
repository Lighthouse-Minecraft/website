<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('back link points to ACP when from=acp is present', function () {
    $user = User::factory()->admin()->create();
    $blog = Blog::factory()->for($user, 'author')->create();

    $this->actingAs($user)
        ->get(route('blogs.show', ['id' => $blog->id, 'from' => 'acp']))
        ->assertSee(route('acp.index'));
})->done(assignee: 'ghostridr');

it('back link points to index by default', function () {
    $user = User::factory()->create();
    $blog = Blog::factory()->for($user, 'author')->create();

    $this->actingAs($user)
        ->get(route('blogs.show', $blog->id))
        ->assertSee(route('blogs.index'));
})->done(assignee: 'ghostridr');
