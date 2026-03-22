<?php

declare(strict_types=1);

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('blog', 'livewire');

it('denies access to regular users', function () {
    $user = User::factory()->create();
    loginAs($user);

    $this->get(route('blog.manage'))->assertForbidden();
});

it('allows blog authors to access the page', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    $this->get(route('blog.manage'))->assertOk();
});

it('allows admins to access the page', function () {
    $admin = loginAsAdmin();

    $this->get(route('blog.manage'))->assertOk();
});

it('displays existing posts in the list', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    $post = BlogPost::factory()->create(['title' => 'Test Blog Post Title']);

    $this->get(route('blog.manage'))->assertSee('Test Blog Post Title');
});

it('creates a blog post via the editor page', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    Volt::test('blog.editor')
        ->set('postTitle', 'New Livewire Post')
        ->set('postBody', 'This is the body of the new post with enough content.')
        ->call('savePost');

    $this->assertDatabaseHas('blog_posts', [
        'title' => 'New Livewire Post',
        'author_id' => $author->id,
    ]);
});

it('edits a blog post via the editor page', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);
    $post = BlogPost::factory()->create(['author_id' => $author->id, 'title' => 'Original Title']);

    Volt::test('blog.editor', ['post' => $post->id])
        ->set('postTitle', 'Updated Title')
        ->call('savePost');

    expect($post->fresh()->title)->toBe('Updated Title');
});

it('creates a category via the component', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    Volt::test('blog.manage')
        ->set('categoryName', 'News')
        ->set('categorySlug', 'news')
        ->call('saveCategory');

    $this->assertDatabaseHas('blog_categories', [
        'name' => 'News',
        'slug' => 'news',
    ]);
});

it('creates a tag via the component', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    Volt::test('blog.manage')
        ->set('newTagName', 'minecraft')
        ->call('createTag');

    $this->assertDatabaseHas('blog_tags', [
        'name' => 'minecraft',
        'slug' => 'minecraft',
    ]);
});

it('deletes a post via the component', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);
    $post = BlogPost::factory()->create(['author_id' => $author->id]);

    Volt::test('blog.manage')
        ->call('deletePost', $post->id);

    expect(BlogPost::find($post->id))->toBeNull();
});

it('deletes a category via the component', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);
    $category = BlogCategory::factory()->create();

    Volt::test('blog.manage')
        ->call('deleteCategory', $category->id);

    expect(BlogCategory::find($category->id))->toBeNull();
});

it('prevents deleting a category that has posts', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);
    $category = BlogCategory::factory()->create();
    BlogPost::factory()->create(['category_id' => $category->id]);

    Volt::test('blog.manage')
        ->call('deleteCategory', $category->id);

    expect(BlogCategory::find($category->id))->not->toBeNull();
});

it('deletes a tag via the component', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);
    $tag = BlogTag::factory()->create();

    Volt::test('blog.manage')
        ->call('deleteTag', $tag->id);

    expect(BlogTag::find($tag->id))->toBeNull();
});

it('filters posts by status', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    BlogPost::factory()->create(['title' => 'Draft Post']);
    BlogPost::factory()->published()->create(['title' => 'Published Post']);

    Volt::test('blog.manage')
        ->set('statusFilter', 'published')
        ->assertSee('Published Post')
        ->assertDontSee('Draft Post');
});

it('searches posts by title', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    BlogPost::factory()->create(['title' => 'Unique Special Title']);
    BlogPost::factory()->create(['title' => 'Other Post']);

    Volt::test('blog.manage')
        ->set('search', 'Unique Special')
        ->assertSee('Unique Special Title')
        ->assertDontSee('Other Post');
});

it('opens preview modal with rendered markdown on editor page', function () {
    $author = User::factory()->withRole('Blog - Author')->create();
    loginAs($author);

    Volt::test('blog.editor')
        ->set('postTitle', 'Preview Test')
        ->set('postBody', '**Bold text** and *italic*')
        ->call('openPreviewModal')
        ->assertSet('previewHtml', fn ($html) => str_contains($html, '<strong>Bold text</strong>'));
});
