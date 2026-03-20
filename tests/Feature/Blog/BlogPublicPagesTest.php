<?php

declare(strict_types=1);

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;

uses()->group('blog', 'public');

// === Blog Index ===

it('returns 200 for blog index without authentication', function () {
    $this->get(route('blog.index'))->assertOk();
});

it('shows published posts on the blog index', function () {
    $post = BlogPost::factory()->published()->create(['title' => 'Published Post Title']);

    $this->get(route('blog.index'))->assertSee('Published Post Title');
});

it('does not show draft posts on the blog index', function () {
    BlogPost::factory()->create(['title' => 'Draft Post Title', 'status' => BlogPostStatus::Draft]);

    $this->get(route('blog.index'))->assertDontSee('Draft Post Title');
});

it('does not show scheduled posts on the blog index', function () {
    BlogPost::factory()->scheduled()->create(['title' => 'Scheduled Post Title']);

    $this->get(route('blog.index'))->assertDontSee('Scheduled Post Title');
});

it('does not show archived posts on the blog index', function () {
    BlogPost::factory()->archived()->create(['title' => 'Archived Post Title']);

    $this->get(route('blog.index'))->assertDontSee('Archived Post Title');
});

it('paginates the blog index', function () {
    BlogPost::factory()->published()->count(15)->create();

    $this->get(route('blog.index'))->assertOk();
});

// === Blog Index Filtering ===

it('filters posts by category', function () {
    $category = BlogCategory::factory()->create(['slug' => 'news']);
    $inCategory = BlogPost::factory()->published()->create([
        'title' => 'In Category',
        'category_id' => $category->id,
    ]);
    $outOfCategory = BlogPost::factory()->published()->create(['title' => 'Out Of Category']);

    $response = $this->get(route('blog.category', 'news'));
    $response->assertOk()
        ->assertSee('In Category')
        ->assertDontSee('Out Of Category');
});

it('returns 404 for non-existent category slug', function () {
    $this->get(route('blog.category', 'nonexistent'))->assertNotFound();
});

it('filters posts by tag', function () {
    $tag = BlogTag::factory()->create(['slug' => 'laravel']);
    $tagged = BlogPost::factory()->published()->create(['title' => 'Tagged Post']);
    $tagged->tags()->attach($tag);

    $untagged = BlogPost::factory()->published()->create(['title' => 'Untagged Post']);

    $response = $this->get(route('blog.tag', 'laravel'));
    $response->assertOk()
        ->assertSee('Tagged Post')
        ->assertDontSee('Untagged Post');
});

it('returns 404 for non-existent tag slug', function () {
    $this->get(route('blog.tag', 'nonexistent'))->assertNotFound();
});

it('filters posts by author', function () {
    $author = User::factory()->create(['name' => 'Jane Author', 'slug' => 'jane-author']);
    $byAuthor = BlogPost::factory()->published()->create([
        'title' => 'Author Post',
        'author_id' => $author->id,
    ]);

    $other = BlogPost::factory()->published()->create(['title' => 'Other Post']);

    $response = $this->get(route('blog.author', 'jane-author'));
    $response->assertOk()
        ->assertSee('Author Post')
        ->assertDontSee('Other Post');
});

it('returns 404 for non-existent author slug', function () {
    $this->get(route('blog.author', 'nonexistent'))->assertNotFound();
});

// === Single Post ===

it('returns 200 for a published blog post', function () {
    $post = BlogPost::factory()->published()->create([
        'title' => 'My Great Post',
        'slug' => 'my-great-post',
        'body' => 'This is the **body** of my post.',
    ]);

    $response = $this->get(route('blog.show', 'my-great-post'));
    $response->assertOk()
        ->assertSee('My Great Post')
        ->assertSee('This is the');
});

it('renders markdown in the post body', function () {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'markdown-test',
        'body' => 'This is **bold** text.',
    ]);

    $response = $this->get(route('blog.show', 'markdown-test'));
    $response->assertOk()
        ->assertSee('<strong>bold</strong>', false);
});

it('returns 404 for a non-existent post slug', function () {
    $this->get(route('blog.show', 'nonexistent-slug'))->assertNotFound();
});

it('returns 404 for a draft post', function () {
    $post = BlogPost::factory()->create([
        'slug' => 'draft-post',
        'status' => BlogPostStatus::Draft,
    ]);

    $this->get(route('blog.show', 'draft-post'))->assertNotFound();
});

it('shows removed message for soft-deleted posts', function () {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'deleted-post',
    ]);
    $post->delete();

    $response = $this->get(route('blog.show', 'deleted-post'));
    $response->assertOk()
        ->assertSee('This post has been removed');
});

it('displays author name linked to author page', function () {
    $author = User::factory()->create(['name' => 'Blog Writer', 'slug' => 'blog-writer']);
    $post = BlogPost::factory()->published()->create([
        'slug' => 'author-link-test',
        'author_id' => $author->id,
    ]);

    $response = $this->get(route('blog.show', 'author-link-test'));
    $response->assertOk()
        ->assertSee('Blog Writer')
        ->assertSee(route('blog.author', 'blog-writer'));
});

it('displays category on the post page', function () {
    $category = BlogCategory::factory()->create(['name' => 'Tutorials', 'slug' => 'tutorials']);
    $post = BlogPost::factory()->published()->create([
        'slug' => 'category-display-test',
        'category_id' => $category->id,
    ]);

    $response = $this->get(route('blog.show', 'category-display-test'));
    $response->assertOk()->assertSee('Tutorials');
});

it('displays tags on the post page', function () {
    $tag = BlogTag::factory()->create(['name' => 'PHP', 'slug' => 'php']);
    $post = BlogPost::factory()->published()->create(['slug' => 'tag-display-test']);
    $post->tags()->attach($tag);

    $response = $this->get(route('blog.show', 'tag-display-test'));
    $response->assertOk()->assertSee('PHP');
});

// === SEO Meta Tags ===

it('includes meta description on post pages', function () {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'seo-test',
        'meta_description' => 'A brief SEO description for testing purposes.',
    ]);

    $response = $this->get(route('blog.show', 'seo-test'));
    $response->assertOk()
        ->assertSee('<meta name="description" content="A brief SEO description for testing purposes."', false);
});

it('includes Open Graph tags on post pages', function () {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'og-test',
        'title' => 'OG Test Post',
        'meta_description' => 'OG description.',
    ]);

    $response = $this->get(route('blog.show', 'og-test'));
    $response->assertOk()
        ->assertSee('og:type', false)
        ->assertSee('og:title', false)
        ->assertSee('og:description', false)
        ->assertSee('og:url', false);
});

it('includes Twitter Card tags on post pages', function () {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'twitter-test',
        'title' => 'Twitter Card Test',
        'meta_description' => 'Twitter description.',
    ]);

    $response = $this->get(route('blog.show', 'twitter-test'));
    $response->assertOk()
        ->assertSee('twitter:card', false)
        ->assertSee('twitter:title', false)
        ->assertSee('twitter:description', false);
});

it('includes JSON-LD Article structured data on post pages', function () {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'jsonld-test',
        'title' => 'JSON-LD Test Post',
    ]);

    $response = $this->get(route('blog.show', 'jsonld-test'));
    $response->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type": "Article"', false);
});

// === Social Sharing ===

it('displays social sharing buttons on post pages', function () {
    $post = BlogPost::factory()->published()->create(['slug' => 'share-test']);

    $response = $this->get(route('blog.show', 'share-test'));
    $response->assertOk()
        ->assertSee('Share this post')
        ->assertSee('twitter.com/intent/tweet', false)
        ->assertSee('facebook.com/sharer', false)
        ->assertSee('Copy Link');
});

// === RSS Feed ===

it('returns valid RSS XML at the rss route', function () {
    $post = BlogPost::factory()->published()->create([
        'title' => 'RSS Test Post',
        'slug' => 'rss-test-post',
        'meta_description' => 'RSS description.',
    ]);

    $response = $this->get(route('blog.rss'));
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<rss version="2.0"', false)
        ->assertSee('RSS Test Post')
        ->assertSee(route('blog.show', 'rss-test-post'));
});

it('only includes published posts in the rss feed', function () {
    BlogPost::factory()->published()->create(['title' => 'Published RSS Post']);
    BlogPost::factory()->create(['title' => 'Draft RSS Post', 'status' => BlogPostStatus::Draft]);

    $response = $this->get(route('blog.rss'));
    $response->assertOk()
        ->assertSee('Published RSS Post')
        ->assertDontSee('Draft RSS Post');
});

it('includes author and category in rss items', function () {
    $author = User::factory()->create(['name' => 'RSS Author']);
    $category = BlogCategory::factory()->create(['name' => 'RSS Category']);
    BlogPost::factory()->published()->create([
        'author_id' => $author->id,
        'category_id' => $category->id,
    ]);

    $response = $this->get(route('blog.rss'));
    $response->assertOk()
        ->assertSee('RSS Author')
        ->assertSee('RSS Category');
});

// === Sitemap ===

it('returns valid sitemap XML', function () {
    $post = BlogPost::factory()->published()->create(['slug' => 'sitemap-post']);

    $response = $this->get(route('blog.sitemap'));
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset', false)
        ->assertSee(route('blog.show', 'sitemap-post'));
});

it('includes published posts in the sitemap', function () {
    $published = BlogPost::factory()->published()->create(['slug' => 'sitemap-published']);
    $draft = BlogPost::factory()->create(['slug' => 'sitemap-draft', 'status' => BlogPostStatus::Draft]);

    $response = $this->get(route('blog.sitemap'));
    $response->assertOk()
        ->assertSee(route('blog.show', 'sitemap-published'))
        ->assertDontSee(route('blog.show', 'sitemap-draft'));
});

it('includes categories with include_in_sitemap flag', function () {
    $included = BlogCategory::factory()->create(['slug' => 'included-cat', 'include_in_sitemap' => true]);
    $excluded = BlogCategory::factory()->create(['slug' => 'excluded-cat', 'include_in_sitemap' => false]);

    $response = $this->get(route('blog.sitemap'));
    $response->assertOk()
        ->assertSee(route('blog.category', 'included-cat'))
        ->assertDontSee(route('blog.category', 'excluded-cat'));
});

it('includes tags with include_in_sitemap flag', function () {
    $included = BlogTag::factory()->create(['slug' => 'included-tag', 'include_in_sitemap' => true]);
    $excluded = BlogTag::factory()->create(['slug' => 'excluded-tag', 'include_in_sitemap' => false]);

    $response = $this->get(route('blog.sitemap'));
    $response->assertOk()
        ->assertSee(route('blog.tag', 'included-tag'))
        ->assertDontSee(route('blog.tag', 'excluded-tag'));
});

// === No Auth Required ===

it('allows unauthenticated access to all public blog routes', function () {
    $category = BlogCategory::factory()->create(['slug' => 'test-cat']);
    $tag = BlogTag::factory()->create(['slug' => 'test-tag']);
    $author = User::factory()->create(['slug' => 'test-author']);
    $post = BlogPost::factory()->published()->create([
        'slug' => 'test-post',
        'category_id' => $category->id,
        'author_id' => $author->id,
    ]);
    $post->tags()->attach($tag);

    $this->get(route('blog.index'))->assertOk();
    $this->get(route('blog.show', 'test-post'))->assertOk();
    $this->get(route('blog.category', 'test-cat'))->assertOk();
    $this->get(route('blog.tag', 'test-tag'))->assertOk();
    $this->get(route('blog.author', 'test-author'))->assertOk();
    $this->get(route('blog.rss'))->assertOk();
    $this->get(route('blog.sitemap'))->assertOk();
});

// === Sidebar Navigation ===

it('shows blog link in sidebar for authenticated users', function () {
    $user = User::factory()->create();
    loginAs($user);

    $post = BlogPost::factory()->published()->create();

    $response = $this->get(route('blog.index'));
    $response->assertOk()->assertSee('Blog');
});
