<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Content Forms Render', function () {
    it('renders blog create form', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('acp.blogs.create'))
            ->assertSuccessful()
            ->assertSee('Create New Blog');
    })->done('ghostridr');

    it('renders blog edit form', function () {
        $user = User::factory()->admin()->create();
        $blog = Blog::factory()->for($user, 'author')->create();

        $this->actingAs($user)
            ->get(route('acp.blogs.edit', ['id' => $blog->id]))
            ->assertSuccessful()
            ->assertSee('Edit Blog');
    })->done('ghostridr');

    it('renders announcement create form', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('acp.announcements.create'))
            ->assertSuccessful()
            ->assertSee('Create New Announcement');
    })->done('ghostridr');

    it('renders announcement edit form', function () {
        $user = User::factory()->admin()->create();
        $announcement = Announcement::factory()->for($user, 'author')->create();

        $this->actingAs($user)
            ->get(route('acp.announcements.edit', ['id' => $announcement->id]))
            ->assertSuccessful()
            ->assertSee('Edit Announcement');
    })->done('ghostridr');
})->done('ghostridr');
