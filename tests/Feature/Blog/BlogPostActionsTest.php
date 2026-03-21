<?php

declare(strict_types=1);

use App\Actions\CreateBlogPost;
use App\Actions\DeleteBlogPost;
use App\Actions\GenerateBlogPostSlug;
use App\Actions\UpdateBlogPost;
use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;

uses()->group('blog', 'actions');

// === GenerateBlogPostSlug ===

it('generates a slug from title', function () {
    $slug = GenerateBlogPostSlug::run('My First Blog Post');

    expect($slug)->toBe('my-first-blog-post');
});

it('handles slug collisions', function () {
    BlogPost::factory()->create(['slug' => 'my-post']);

    $slug = GenerateBlogPostSlug::run('My Post');

    expect($slug)->toBe('my-post-2');
});

it('handles multiple slug collisions', function () {
    BlogPost::factory()->create(['slug' => 'test-post']);
    BlogPost::factory()->create(['slug' => 'test-post-2']);

    $slug = GenerateBlogPostSlug::run('Test Post');

    expect($slug)->toBe('test-post-3');
});

it('excludes the current post when checking slug uniqueness', function () {
    $post = BlogPost::factory()->create(['slug' => 'my-post']);

    $slug = GenerateBlogPostSlug::run('My Post', $post->id);

    expect($slug)->toBe('my-post');
});

it('defaults to post slug when title is empty', function () {
    $slug = GenerateBlogPostSlug::run('');

    expect($slug)->toBe('post');
});

it('checks against soft-deleted posts for slug uniqueness', function () {
    $post = BlogPost::factory()->create(['slug' => 'deleted-post']);
    $post->delete();

    $slug = GenerateBlogPostSlug::run('Deleted Post');

    expect($slug)->toBe('deleted-post-2');
});

// === CreateBlogPost ===

it('creates a blog post in draft status', function () {
    $author = User::factory()->create();

    $post = CreateBlogPost::run($author, [
        'title' => 'My Test Post',
        'body' => 'This is the body of the post.',
    ]);

    expect($post->title)->toBe('My Test Post')
        ->and($post->slug)->toBe('my-test-post')
        ->and($post->body)->toBe('This is the body of the post.')
        ->and($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->author_id)->toBe($author->id);
});

it('creates a blog post with category and tags', function () {
    $author = User::factory()->create();
    $category = BlogCategory::factory()->create();
    $tags = BlogTag::factory()->count(2)->create();

    $post = CreateBlogPost::run($author, [
        'title' => 'Tagged Post',
        'body' => 'Post body content here.',
        'category_id' => $category->id,
        'tag_ids' => $tags->pluck('id')->toArray(),
    ]);

    expect($post->category_id)->toBe($category->id)
        ->and($post->tags)->toHaveCount(2);
});

it('creates a blog post with meta description and images', function () {
    $author = User::factory()->create();
    $heroImage = \App\Models\BlogImage::factory()->create();
    $ogImage = \App\Models\BlogImage::factory()->create();

    $post = CreateBlogPost::run($author, [
        'title' => 'SEO Post',
        'body' => 'Body content.',
        'meta_description' => 'A great post about things.',
        'hero_image_id' => $heroImage->id,
        'og_image_id' => $ogImage->id,
    ]);

    expect($post->meta_description)->toBe('A great post about things.')
        ->and($post->hero_image_id)->toBe($heroImage->id)
        ->and($post->og_image_id)->toBe($ogImage->id);
});

it('records activity when creating a blog post', function () {
    $author = User::factory()->create();

    $post = CreateBlogPost::run($author, [
        'title' => 'Activity Log Test',
        'body' => 'Testing activity logging.',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogPost::class,
        'subject_id' => $post->id,
        'action' => 'blog_post_created',
    ]);
});

// === UpdateBlogPost ===

it('updates a blog post title and regenerates slug', function () {
    $post = BlogPost::factory()->create(['title' => 'Old Title', 'slug' => 'old-title']);

    $updated = UpdateBlogPost::run($post, ['title' => 'New Title']);

    expect($updated->title)->toBe('New Title')
        ->and($updated->slug)->toBe('new-title');
});

it('updates blog post body', function () {
    $post = BlogPost::factory()->create(['body' => 'Old body']);

    $updated = UpdateBlogPost::run($post, ['body' => 'New body content']);

    expect($updated->body)->toBe('New body content');
});

it('sets is_edited flag when updating a published post', function () {
    $post = BlogPost::factory()->published()->create(['is_edited' => false]);

    $updated = UpdateBlogPost::run($post, ['body' => 'Updated published body']);

    expect($updated->is_edited)->toBeTrue();
});

it('does not set is_edited flag when updating a draft post', function () {
    $post = BlogPost::factory()->create(['is_edited' => false]);

    $updated = UpdateBlogPost::run($post, ['body' => 'Updated draft body']);

    expect($updated->is_edited)->toBeFalse();
});

it('syncs tags on update', function () {
    $post = BlogPost::factory()->create();
    $tags = BlogTag::factory()->count(3)->create();
    $post->tags()->sync($tags->take(2)->pluck('id'));

    $updated = UpdateBlogPost::run($post, [
        'tag_ids' => [$tags[2]->id],
    ]);

    expect($updated->tags)->toHaveCount(1)
        ->and($updated->tags->first()->id)->toBe($tags[2]->id);
});

it('records activity when updating a blog post', function () {
    $post = BlogPost::factory()->create();

    UpdateBlogPost::run($post, ['body' => 'Updated body']);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogPost::class,
        'subject_id' => $post->id,
        'action' => 'blog_post_updated',
    ]);
});

// === DeleteBlogPost ===

it('soft deletes a blog post', function () {
    $post = BlogPost::factory()->create();

    DeleteBlogPost::run($post);

    expect(BlogPost::find($post->id))->toBeNull()
        ->and(BlogPost::withTrashed()->find($post->id))->not->toBeNull();
});

it('records activity when deleting a blog post', function () {
    $post = BlogPost::factory()->create();

    DeleteBlogPost::run($post);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogPost::class,
        'subject_id' => $post->id,
        'action' => 'blog_post_deleted',
    ]);
});
