<?php

declare(strict_types=1);

use App\Jobs\CleanupUnreferencedBlogImages;
use App\Models\BlogImage;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Storage;

uses()->group('blog', 'blog-images', 'blog-cleanup');

it('deletes images unreferenced for 30+ days', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/old.jpg', 'data');

    $image = BlogImage::factory()->create([
        'path' => 'blog/images/old.jpg',
        'unreferenced_at' => now()->subDays(31),
    ]);

    (new CleanupUnreferencedBlogImages)->handle();

    $this->assertDatabaseMissing('blog_images', ['id' => $image->id]);
    Storage::disk(config('filesystems.public_disk'))->assertMissing('blog/images/old.jpg');
});

it('does not delete images unreferenced for less than 30 days', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/recent.jpg', 'data');

    $image = BlogImage::factory()->create([
        'path' => 'blog/images/recent.jpg',
        'unreferenced_at' => now()->subDays(15),
    ]);

    (new CleanupUnreferencedBlogImages)->handle();

    $this->assertDatabaseHas('blog_images', ['id' => $image->id]);
    Storage::disk(config('filesystems.public_disk'))->assertExists('blog/images/recent.jpg');
});

it('does not delete images that still have references', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/referenced.jpg', 'data');

    $image = BlogImage::factory()->create([
        'path' => 'blog/images/referenced.jpg',
        'unreferenced_at' => null,
    ]);

    $post = BlogPost::factory()->create();
    $image->posts()->attach($post->id);

    (new CleanupUnreferencedBlogImages)->handle();

    $this->assertDatabaseHas('blog_images', ['id' => $image->id]);
    Storage::disk(config('filesystems.public_disk'))->assertExists('blog/images/referenced.jpg');
});

it('deletes S3 file when cleaning up an unreferenced image', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/to-delete.jpg', 'data');

    $image = BlogImage::factory()->create([
        'path' => 'blog/images/to-delete.jpg',
        'unreferenced_at' => now()->subDays(45),
    ]);

    (new CleanupUnreferencedBlogImages)->handle();

    Storage::disk(config('filesystems.public_disk'))->assertMissing('blog/images/to-delete.jpg');
});

it('logs activity for each deleted image', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/logged.jpg', 'data');

    $image = BlogImage::factory()->create([
        'title' => 'Logged Image',
        'path' => 'blog/images/logged.jpg',
        'unreferenced_at' => now()->subDays(35),
    ]);

    (new CleanupUnreferencedBlogImages)->handle();

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogImage::class,
        'subject_id' => $image->id,
        'action' => 'blog_image_deleted',
    ]);
});

it('handles multiple images with mixed states correctly', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/old-unreferenced.jpg', 'data');
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/recent-unreferenced.jpg', 'data');
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/still-referenced.jpg', 'data');

    $oldUnreferenced = BlogImage::factory()->create([
        'path' => 'blog/images/old-unreferenced.jpg',
        'unreferenced_at' => now()->subDays(60),
    ]);

    $recentUnreferenced = BlogImage::factory()->create([
        'path' => 'blog/images/recent-unreferenced.jpg',
        'unreferenced_at' => now()->subDays(10),
    ]);

    $stillReferenced = BlogImage::factory()->create([
        'path' => 'blog/images/still-referenced.jpg',
        'unreferenced_at' => null,
    ]);

    (new CleanupUnreferencedBlogImages)->handle();

    $this->assertDatabaseMissing('blog_images', ['id' => $oldUnreferenced->id])
        ->assertDatabaseHas('blog_images', ['id' => $recentUnreferenced->id])
        ->assertDatabaseHas('blog_images', ['id' => $stillReferenced->id]);
});

it('deletes images unreferenced for exactly 30 days', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/edge.jpg', 'data');

    $image = BlogImage::factory()->create([
        'path' => 'blog/images/edge.jpg',
        'unreferenced_at' => now()->subDays(30),
    ]);

    (new CleanupUnreferencedBlogImages)->handle();

    $this->assertDatabaseMissing('blog_images', ['id' => $image->id]);
});

it('does not delete images unreferenced for 29 days', function () {
    Storage::fake(config('filesystems.public_disk'));
    Storage::disk(config('filesystems.public_disk'))->put('blog/images/not-yet.jpg', 'data');

    $image = BlogImage::factory()->create([
        'path' => 'blog/images/not-yet.jpg',
        'unreferenced_at' => now()->subDays(29),
    ]);

    (new CleanupUnreferencedBlogImages)->handle();

    $this->assertDatabaseHas('blog_images', ['id' => $image->id]);
});
