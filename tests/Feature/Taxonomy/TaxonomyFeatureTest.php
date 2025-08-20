<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

use function Pest\Laravel\get;

describe('Taxonomy Features', function () {
    it('shows categories index for admins and authorized users', function () {
        $admin = User::factory()->admin()->create();
        Category::factory()->count(3)->create();

        $this->actingAs($admin);
        $res = get(route('taxonomy.categories.index'));
        $res->assertOk();
        $res->assertSee('Category Index');
    })->done(assignee: 'ghostridr');

    it('shows tags index for admins and authorized users', function () {
        $admin = User::factory()->admin()->create();
        Tag::factory()->count(3)->create();

        $this->actingAs($admin);
        $res = get(route('taxonomy.tags.index'));
        $res->assertOk();
        $res->assertSee('Tag Index');
    })->done(assignee: 'ghostridr');

    it('shows category show page', function () {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin);
        $res = get(route('taxonomy.categories.show', ['id' => $category->id]));
        $res->assertOk();
        $res->assertSee(e($category->name));
    })->done(assignee: 'ghostridr');

    it('shows tag show page', function () {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();

        $this->actingAs($admin);
        $res = get(route('taxonomy.tags.show', ['id' => $tag->id]));
        $res->assertOk();
        $res->assertSee(e($tag->name));
    })->done(assignee: 'ghostridr');

    it('lists blogs by category', function () {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->withBlogs(2)->create();

        $this->actingAs($admin);
        $res = get(route('taxonomy.categories.blogs', ['id' => $category->id]));
        $res->assertOk();
        foreach ($category->blogs as $blog) {
            $res->assertSee(e($blog->title));
        }
    })->done(assignee: 'ghostridr');

    it('lists blogs by tag', function () {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $blogs = Blog::factory()->count(2)->create();
        foreach ($blogs as $b) {
            $b->tags()->attach($tag->id);
        }

        $this->actingAs($admin);
        $res = get(route('taxonomy.tags.blogs', ['id' => $tag->id]));
        $res->assertOk();
        foreach ($blogs as $blog) {
            $res->assertSee(e($blog->title));
        }
    })->done(assignee: 'ghostridr');

    it('lists announcements by category', function () {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->withAnnouncements(2)->create();

        $this->actingAs($admin);
        $res = get(route('taxonomy.categories.announcements', ['id' => $category->id]));
        $res->assertOk();
        foreach ($category->announcements as $announcement) {
            $res->assertSee(e($announcement->title));
        }
    })->done(assignee: 'ghostridr');

    it('lists announcements by tag', function () {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $announcements = Announcement::factory()->count(2)->create();
        foreach ($announcements as $a) {
            $a->tags()->attach($tag->id);
        }

        $this->actingAs($admin);
        $res = get(route('taxonomy.tags.announcements', ['id' => $tag->id]));
        $res->assertOk();
        foreach ($announcements as $a) {
            $res->assertSee(e($a->title));
        }
    })->done(assignee: 'ghostridr');

    it('validates category store and update', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Store
        $store = $this->post(route('taxonomy.categories.store'), ['name' => 'My Cat']);
        $store->assertCreated();
        $categoryId = Category::where('name', 'My Cat')->value('id');

        // Update
        $update = $this->put(route('taxonomy.categories.update', ['id' => $categoryId]), ['name' => 'My Cat 2']);
        $update->assertRedirect();
    })->done(assignee: 'ghostridr');

    it('validates tag store and update', function () {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Store
        $store = $this->post(route('taxonomy.tags.store'), ['name' => 'My Tag']);
        $store->assertCreated();
        $tagId = Tag::where('name', 'My Tag')->value('id');

        // Update
        $update = $this->put(route('taxonomy.tags.update', ['id' => $tagId]), ['name' => 'My Tag 2']);
        $update->assertRedirect();
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
