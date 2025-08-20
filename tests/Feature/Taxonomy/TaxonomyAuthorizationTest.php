<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Taxonomy Authorization', function () {
    it('allows admins to view taxonomy categories index', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $res = $this->get(route('taxonomy.categories.index'));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from viewing taxonomy categories index', function () {
        $res = $this->get(route('taxonomy.categories.index'));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('allows any authenticated user to view taxonomy tags index', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $res = $this->get(route('taxonomy.tags.index'));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from viewing taxonomy tags index', function () {
        $res = $this->get(route('taxonomy.tags.index'));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('allows any authenticated user to view category show', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::factory()->create();
        $res = $this->get(route('taxonomy.categories.show', $category->id));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from viewing category show', function () {
        $category = Category::factory()->create();
        $res = $this->get(route('taxonomy.categories.show', $category->id));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('allows any authenticated user to view tag show', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $tag = Tag::factory()->create();
        $res = $this->get(route('taxonomy.tags.show', $tag->id));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from viewing tag show', function () {
        $tag = Tag::factory()->create();
        $res = $this->get(route('taxonomy.tags.show', $tag->id));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('allows any authenticated user to list blogs by category', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::factory()->withBlogs(1)->create();
        $res = $this->get(route('taxonomy.categories.blogs', $category->id));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from listing blogs by category', function () {
        $category = Category::factory()->withBlogs(1)->create();
        $res = $this->get(route('taxonomy.categories.blogs', $category->id));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('allows any authenticated user to list blogs by tag', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $tag = Tag::factory()->create();
        $blog = Blog::factory()->create();
        $blog->tags()->attach($tag->id);
        $res = $this->get(route('taxonomy.tags.blogs', $tag->id));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from listing blogs by tag', function () {
        $tag = Tag::factory()->create();
        $blog = Blog::factory()->create();
        $blog->tags()->attach($tag->id);
        $res = $this->get(route('taxonomy.tags.blogs', $tag->id));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');
    it('allows any authenticated user to list announcements by category', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::factory()->withAnnouncements(1)->create();
        $res = $this->get(route('taxonomy.categories.announcements', $category->id));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from listing announcements by category', function () {
        $category = Category::factory()->withAnnouncements(1)->create();
        $res = $this->get(route('taxonomy.categories.announcements', $category->id));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');

    it('allows any authenticated user to list announcements by tag', function () {
        $user = User::factory()->create();
        $this->actingAs($user);
        $tag = Tag::factory()->create();
        $announcement = Announcement::factory()->create();
        $announcement->tags()->attach($tag->id);
        $res = $this->get(route('taxonomy.tags.announcements', $tag->id));
        $res->assertOk();
    })->done(assignee: 'ghostridr');

    it('forbids guests from listing announcements by tag', function () {
        $tag = Tag::factory()->create();
        $announcement = Announcement::factory()->create();
        $announcement->tags()->attach($tag->id);
        $res = $this->get(route('taxonomy.tags.announcements', $tag->id));
        $res->assertForbidden();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
