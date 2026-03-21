<?php

declare(strict_types=1);

use App\Actions\DeleteBlogImage;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses()->group('blog', 'blog-images', 'acp');

// === Table Rendering ===

it('displays blog images table for users with manage-blog gate', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image = BlogImage::factory()->create(['title' => 'Test Image Title']);

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->assertOk()
        ->assertSee('Test Image Title')
        ->assertSee('Blog Images');
});

it('displays image metadata in the table', function () {
    $uploader = User::factory()->create(['name' => 'ImageUploader']);
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image = BlogImage::factory()->create([
        'title' => 'Sunset Photo',
        'alt_text' => 'A beautiful sunset over the ocean',
        'uploaded_by' => $uploader->id,
    ]);

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->assertSee('Sunset Photo')
        ->assertSee('A beautiful sunset over the ocean')
        ->assertSee('ImageUploader');
});

it('displays usage count for images with references', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image = BlogImage::factory()->create(['title' => 'Referenced Image']);
    $post1 = BlogPost::factory()->create();
    $post2 = BlogPost::factory()->create();
    $image->posts()->attach([$post1->id, $post2->id]);

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->assertSee('2 posts');
});

it('displays unused badge for images with zero references', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    BlogImage::factory()->create(['title' => 'Unused Image']);

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->assertSee('Unused');
});

it('paginates blog images', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    BlogImage::factory()->count(25)->create();

    $component = \Livewire\Livewire::test('admin-manage-blog-images-page');

    $images = $component->viewData('images');
    expect($images)->toHaveCount(20);
});

// === Search ===

it('filters images by title search', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    BlogImage::factory()->create(['title' => 'Sunset Photo']);
    BlogImage::factory()->create(['title' => 'Mountain View']);

    $component = \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->set('search', 'Sunset');

    $images = $component->viewData('images');
    expect($images)->toHaveCount(1)
        ->and($images->first()->title)->toBe('Sunset Photo');
});

it('shows empty state when no images match search', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    BlogImage::factory()->create(['title' => 'Sunset Photo']);

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->set('search', 'Nonexistent')
        ->assertSee('No blog images found.');
});

// === Delete (allowed) ===

it('allows deleting an image with zero references', function () {
    Storage::fake(config('filesystems.public_disk'));

    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image = BlogImage::factory()->create(['title' => 'Deletable Image']);

    Storage::disk(config('filesystems.public_disk'))->put($image->path, 'fake content');

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->call('deleteImage', $image->id);

    $this->assertDatabaseMissing('blog_images', ['id' => $image->id]);
    Storage::disk(config('filesystems.public_disk'))->assertMissing($image->path);
});

it('records activity when a blog image is deleted', function () {
    Storage::fake(config('filesystems.public_disk'));

    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image = BlogImage::factory()->create(['title' => 'Activity Delete Image']);
    $imageId = $image->id;

    Storage::disk(config('filesystems.public_disk'))->put($image->path, 'fake content');

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->call('deleteImage', $image->id);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogImage::class,
        'subject_id' => $imageId,
        'action' => 'blog_image_deleted',
    ]);
});

// === Delete (blocked) ===

it('blocks deleting an image with active references via component', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image = BlogImage::factory()->create(['title' => 'Referenced Image']);
    $post = BlogPost::factory()->create();
    $image->posts()->attach($post->id);

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->call('deleteImage', $image->id);

    $this->assertDatabaseHas('blog_images', ['id' => $image->id]);
});

it('blocks deleting an image with active references via action', function () {
    $image = BlogImage::factory()->create();
    $post = BlogPost::factory()->create();
    $image->posts()->attach($post->id);

    expect(fn () => DeleteBlogImage::run($image))
        ->toThrow(\RuntimeException::class, 'Cannot delete a blog image that is still referenced by posts.');

    $this->assertDatabaseHas('blog_images', ['id' => $image->id]);
});

// === Authorization ===

it('denies access to blog images tab for users without manage-blog gate', function () {
    $user = User::factory()->create();
    loginAs($user);

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->call('deleteImage', 1)
        ->assertForbidden();
});

it('denies delete action for users without manage-blog gate', function () {
    $user = User::factory()->create();
    loginAs($user);

    $image = BlogImage::factory()->create();

    \Livewire\Livewire::test('admin-manage-blog-images-page')
        ->call('deleteImage', $image->id)
        ->assertForbidden();

    $this->assertDatabaseHas('blog_images', ['id' => $image->id]);
});
