<?php

declare(strict_types=1);

use App\Actions\UploadBlogImage;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->group('blog', 'blog-images');

// === UploadBlogImage Action ===

it('creates a blog image record and stores the file', function () {
    Storage::fake(config('filesystems.public_disk'));

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test-photo.jpg');

    $image = UploadBlogImage::run($user, $file, 'Test Photo', 'A test photo description');

    expect($image)->toBeInstanceOf(BlogImage::class)
        ->and($image->title)->toBe('Test Photo')
        ->and($image->alt_text)->toBe('A test photo description')
        ->and($image->uploaded_by)->toBe($user->id)
        ->and($image->path)->toStartWith('blog/images/');

    Storage::disk(config('filesystems.public_disk'))->assertExists($image->path);
});

it('records activity when a blog image is uploaded', function () {
    Storage::fake(config('filesystems.public_disk'));

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('activity-test.jpg');

    $image = UploadBlogImage::run($user, $file, 'Activity Test', 'Alt text');

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => BlogImage::class,
        'subject_id' => $image->id,
        'action' => 'blog_image_uploaded',
    ]);
});

// === BlogImage Model Relationships ===

it('has an uploadedBy relationship', function () {
    $image = BlogImage::factory()->create();

    expect($image->uploadedBy)->toBeInstanceOf(User::class);
});

it('has a posts relationship', function () {
    $image = BlogImage::factory()->create();
    $post = BlogPost::factory()->create();

    $image->posts()->attach($post->id);

    expect($image->fresh()->posts)->toHaveCount(1)
        ->and($image->fresh()->posts->first()->id)->toBe($post->id);
});

// === Image Tag Rendering ===

it('renders an image tag with default alt text', function () {
    $image = BlogImage::factory()->create([
        'alt_text' => 'A beautiful sunset',
        'path' => 'blog/images/sunset.jpg',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "Hello world\n\n{{image:{$image->id}}}\n\nEnd of post",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->toContain('<img src="')
        ->and($rendered)->toContain('alt="A beautiful sunset"')
        ->and($rendered)->toContain('class="rounded-lg"')
        ->and($rendered)->not->toContain('{{image:');
});

it('renders an image tag with override alt text', function () {
    $image = BlogImage::factory()->create([
        'alt_text' => 'Default alt text',
        'path' => 'blog/images/photo.jpg',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "Content\n\n{{image:{$image->id}|Custom override alt}}\n\nMore content",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->toContain('alt="Custom override alt"')
        ->and($rendered)->not->toContain('Default alt text')
        ->and($rendered)->not->toContain('{{image:');
});

it('renders empty string for invalid image ID', function () {
    $post = BlogPost::factory()->create([
        'body' => "Before\n\n{{image:99999}}\n\nAfter",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->not->toContain('{{image:')
        ->and($rendered)->toContain('Before')
        ->and($rendered)->toContain('After');
});

it('renders empty string for missing image ID of zero', function () {
    $post = BlogPost::factory()->create([
        'body' => 'Before {{image:0}} After',
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->not->toContain('{{image:')
        ->and($rendered)->not->toContain('<img');
});

it('resolves multiple image tags in one post body', function () {
    $image1 = BlogImage::factory()->create([
        'alt_text' => 'First image alt',
        'path' => 'blog/images/first.jpg',
    ]);
    $image2 = BlogImage::factory()->create([
        'alt_text' => 'Second image alt',
        'path' => 'blog/images/second.jpg',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "Intro\n\n{{image:{$image1->id}}}\n\nMiddle\n\n{{image:{$image2->id}}}\n\nEnd",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->toContain('alt="First image alt"')
        ->and($rendered)->toContain('alt="Second image alt"')
        ->and($rendered)->not->toContain('{{image:');
});

it('handles a mix of valid and invalid image tags', function () {
    $image = BlogImage::factory()->create([
        'alt_text' => 'Valid image',
        'path' => 'blog/images/valid.jpg',
    ]);

    $post = BlogPost::factory()->create([
        'body' => "{{image:{$image->id}}}\n\n{{image:99999}}",
    ]);

    $rendered = $post->renderBody();

    expect($rendered)->toContain('alt="Valid image"')
        ->and($rendered)->not->toContain('{{image:');
});

// === Authorization ===

it('allows users with manage-blog gate to upload images', function () {
    Storage::fake(config('filesystems.public_disk'));

    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $response = \Livewire\Livewire::test('blog.editor')
        ->assertOk();
});

it('denies upload to users without manage-blog gate', function () {
    $user = User::factory()->create();
    loginAs($user);

    \Livewire\Livewire::test('blog.editor')
        ->assertForbidden();
});

// === Image Gallery ===

it('displays the image gallery with existing images', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image1 = BlogImage::factory()->create(['title' => 'Sunset Photo']);
    $image2 = BlogImage::factory()->create(['title' => 'Mountain View']);

    \Livewire\Livewire::test('blog.editor')
        ->assertSee('Browse Images')
        ->assertSee('Sunset Photo')
        ->assertSee('Mountain View');
});

it('filters gallery images by title search', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    BlogImage::factory()->create(['title' => 'Sunset Photo']);
    BlogImage::factory()->create(['title' => 'Mountain View']);

    $component = \Livewire\Livewire::test('blog.editor')
        ->set('gallerySearch', 'Sunset')
        ->assertSee('Sunset Photo');

    $galleryImages = $component->viewData('galleryImages');
    expect($galleryImages)->toHaveCount(1)
        ->and($galleryImages->first()->title)->toBe('Sunset Photo');
});

it('shows empty state when no gallery images match search', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    BlogImage::factory()->create(['title' => 'Sunset Photo']);

    \Livewire\Livewire::test('blog.editor')
        ->set('gallerySearch', 'Nonexistent')
        ->assertSee('No images match your search.');
});

it('inserts gallery image tag into post body', function () {
    $author = User::factory()->withRole('Blog Author')->create();
    loginAs($author);

    $image = BlogImage::factory()->create(['title' => 'Test Image']);

    \Livewire\Livewire::test('blog.editor')
        ->set('postBody', 'Some content here')
        ->call('insertGalleryImage', $image->id)
        ->assertSet('postBody', "Some content here\n\n{{image:{$image->id}}}\n");
});

it('denies gallery image insertion to users without manage-blog gate', function () {
    $user = User::factory()->create();
    loginAs($user);

    $image = BlogImage::factory()->create();

    \Livewire\Livewire::test('blog.editor')
        ->assertForbidden();
});
