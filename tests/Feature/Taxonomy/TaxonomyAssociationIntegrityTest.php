<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('deleting a category cleans up pivots and does not delete related models', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $category = Category::factory()->create();
    $blogs = Blog::factory()->count(2)->create();
    $anns = Announcement::factory()->count(2)->create();

    foreach ($blogs as $b) {
        $b->categories()->attach($category->id);
    }
    foreach ($anns as $a) {
        $a->categories()->attach($category->id);
    }

    $this->delete(route('taxonomy.categories.destroy', $category->id))->assertRedirect();

    expect(DB::table('blog_category')->count())->toBe(0);
    expect(DB::table('announcement_category')->count())->toBe(0);
    expect(Blog::count())->toBe(2);
    expect(Announcement::count())->toBe(2);
})->done(assignee: 'ghostridr');

it('deleting a tag cleans up pivots and does not delete related models', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tag = Tag::factory()->create();
    $blogs = Blog::factory()->count(2)->create();
    $anns = Announcement::factory()->count(2)->create();

    foreach ($blogs as $b) {
        $b->tags()->attach($tag->id);
    }
    foreach ($anns as $a) {
        $a->tags()->attach($tag->id);
    }

    $this->delete(route('taxonomy.tags.destroy', $tag->id))->assertRedirect();

    expect(DB::table('blog_tag')->count())->toBe(0);
    expect(DB::table('announcement_tag')->count())->toBe(0);
    expect(Blog::count())->toBe(2);
    expect(Announcement::count())->toBe(2);
})->done(assignee: 'ghostridr');
