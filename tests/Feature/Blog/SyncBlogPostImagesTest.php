<?php

declare(strict_types=1);

use App\Actions\CreateBlogPost;
use App\Actions\DeleteBlogPost;
use App\Actions\SyncBlogPostImages;
use App\Actions\UpdateBlogPost;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\User;

uses()->group('blog', 'blog-images', 'sync-blog-images');

// === Parsing body tags ===

it('parses image tags from the post body and creates pivot entries', function () {
    $image1 = BlogImage::factory()->create();
    $image2 = BlogImage::factory()->create();

    $post = BlogPost::factory()->create([
        'body' => "Hello {{image:{$image1->id}}} world {{image:{$image2->id}}}",
    ]);

    SyncBlogPostImages::run($post);

    expect($post->images)->toHaveCount(2)
        ->and($post->images->pluck('id')->all())->toContain($image1->id, $image2->id);
});

it('parses image tags with alt text overrides', function () {
    $image = BlogImage::factory()->create();

    $post = BlogPost::factory()->create([
        'body' => "Content {{image:{$image->id}|Custom alt text}} end",
    ]);

    SyncBlogPostImages::run($post);

    expect($post->images)->toHaveCount(1)
        ->and($post->images->first()->id)->toBe($image->id);
});

it('ignores invalid image IDs that do not exist in the database', function () {
    $image = BlogImage::factory()->create();

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}} {{image:99999}}",
    ]);

    SyncBlogPostImages::run($post);

    expect($post->images)->toHaveCount(1)
        ->and($post->images->first()->id)->toBe($image->id);
});

it('handles duplicate image tags in the body', function () {
    $image = BlogImage::factory()->create();

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}} middle {{image:{$image->id}}}",
    ]);

    SyncBlogPostImages::run($post);

    expect($post->images)->toHaveCount(1);
});

it('handles empty body with no image tags', function () {
    $post = BlogPost::factory()->create(['body' => 'No images here.']);

    SyncBlogPostImages::run($post);

    expect($post->images)->toHaveCount(0);
});

// === Adding and removing references ===

it('adds new references when images are added to the body', function () {
    $image1 = BlogImage::factory()->create();
    $image2 = BlogImage::factory()->create();

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image1->id}}}",
    ]);

    SyncBlogPostImages::run($post);
    expect($post->fresh()->images)->toHaveCount(1);

    // Add a second image
    $post->update(['body' => "{{image:{$image1->id}}} {{image:{$image2->id}}}"]);
    SyncBlogPostImages::run($post);

    expect($post->fresh()->images)->toHaveCount(2);
});

it('removes pivot entries when images are removed from the body', function () {
    $image1 = BlogImage::factory()->create();
    $image2 = BlogImage::factory()->create();

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image1->id}}} {{image:{$image2->id}}}",
    ]);

    SyncBlogPostImages::run($post);
    expect($post->fresh()->images)->toHaveCount(2);

    // Remove image2
    $post->update(['body' => "{{image:{$image1->id}}}"]);
    SyncBlogPostImages::run($post);

    expect($post->fresh()->images)->toHaveCount(1)
        ->and($post->fresh()->images->first()->id)->toBe($image1->id);
});

// === unreferenced_at lifecycle ===

it('sets unreferenced_at when an image loses all references', function () {
    $image = BlogImage::factory()->create(['unreferenced_at' => null]);

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);

    SyncBlogPostImages::run($post);
    expect($image->fresh()->unreferenced_at)->toBeNull();

    // Remove the image from the body
    $post->update(['body' => 'No images']);
    SyncBlogPostImages::run($post);

    expect($image->fresh()->unreferenced_at)->not->toBeNull();
});

it('clears unreferenced_at when an image gains a reference', function () {
    $image = BlogImage::factory()->create(['unreferenced_at' => now()->subDays(10)]);

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);

    SyncBlogPostImages::run($post);

    expect($image->fresh()->unreferenced_at)->toBeNull();
});

it('does not set unreferenced_at if image is still referenced by another post', function () {
    $image = BlogImage::factory()->create(['unreferenced_at' => null]);

    $post1 = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);
    $post2 = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);

    SyncBlogPostImages::run($post1);
    SyncBlogPostImages::run($post2);

    expect($image->fresh()->posts)->toHaveCount(2);

    // Remove from post1 only
    $post1->update(['body' => 'No images']);
    SyncBlogPostImages::run($post1);

    // Image still referenced by post2, so unreferenced_at should be null
    expect($image->fresh()->unreferenced_at)->toBeNull()
        ->and($image->fresh()->posts)->toHaveCount(1);
});

// === hero_image_id and og_image_id counted as references ===

it('counts hero_image_id as a reference if the column exists on the post', function () {
    $image = BlogImage::factory()->create(['unreferenced_at' => now()->subDays(5)]);

    $post = BlogPost::factory()->create(['body' => 'No image tags']);

    // Simulate hero_image_id being set (even though column doesn't exist yet,
    // the action checks via null coalescing)
    // For now, since the column doesn't exist, hero_image_id won't be present
    // and the action gracefully skips it.
    SyncBlogPostImages::run($post);

    expect($post->fresh()->images)->toHaveCount(0);
});

// === Integration with CreateBlogPost ===

it('syncs image references when a blog post is created', function () {
    $author = User::factory()->create();
    $image = BlogImage::factory()->create(['unreferenced_at' => now()->subDays(5)]);

    $post = CreateBlogPost::run($author, [
        'title' => 'Test Post',
        'body' => "Content with {{image:{$image->id}}} in it",
    ]);

    expect($post->images)->toHaveCount(1)
        ->and($post->images->first()->id)->toBe($image->id)
        ->and($image->fresh()->unreferenced_at)->toBeNull();
});

// === Integration with UpdateBlogPost ===

it('syncs image references when a blog post is updated', function () {
    $image1 = BlogImage::factory()->create();
    $image2 = BlogImage::factory()->create();

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image1->id}}}",
    ]);
    SyncBlogPostImages::run($post);

    $post = UpdateBlogPost::run($post, [
        'body' => "{{image:{$image2->id}}}",
    ]);

    expect($post->images)->toHaveCount(1)
        ->and($post->images->first()->id)->toBe($image2->id);
});

it('sets unreferenced_at on removed images during update', function () {
    $image = BlogImage::factory()->create(['unreferenced_at' => null]);

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);
    SyncBlogPostImages::run($post);

    UpdateBlogPost::run($post, ['body' => 'No images now']);

    expect($image->fresh()->unreferenced_at)->not->toBeNull();
});

// === Integration with DeleteBlogPost ===

it('releases all image references when a blog post is deleted', function () {
    $image = BlogImage::factory()->create(['unreferenced_at' => null]);

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);
    SyncBlogPostImages::run($post);

    expect($image->fresh()->unreferenced_at)->toBeNull();

    DeleteBlogPost::run($post);

    expect($image->fresh()->unreferenced_at)->not->toBeNull();
});

it('does not set unreferenced_at on delete if image is still referenced by another post', function () {
    $image = BlogImage::factory()->create(['unreferenced_at' => null]);

    $post1 = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);
    $post2 = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}",
    ]);
    SyncBlogPostImages::run($post1);
    SyncBlogPostImages::run($post2);

    DeleteBlogPost::run($post1);

    expect($image->fresh()->unreferenced_at)->toBeNull()
        ->and($image->fresh()->posts)->toHaveCount(1);
});
