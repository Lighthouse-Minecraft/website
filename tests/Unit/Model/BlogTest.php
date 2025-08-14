<?php

use App\Actions\AcknowledgeBlog;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Blog feature tests for posts
describe('Blog Feature', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // API
    // ───────────────────────────────────────────────────────────────────────────
    describe('API', function () {
        it('can list blogs via web page', function () {
            Blog::factory()->count(3)->create();

            $res = $this->get(route('blogs.index'));

            $res->assertOk();

            // See at least one title in the rendered HTML
            $first = Blog::first();
            $res->assertSee(e($first->title));
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Authorization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Authorization', function () {
        it('allows admin to create a blog', function () {
            $this->withExceptionHandling();
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin);
            $response = $this->post('/blogs', [
                'title' => 'New Blog',
                'content' => 'Content',
                'author_id' => $admin->id,
                'is_published' => true,
            ]);
            expect($response->status())->toBe(201);
        });

        it('prevents non-admin from creating a blog', function () {
            $this->withExceptionHandling();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->post('/blogs', [
                'title' => 'New Blog',
                'content' => 'Content',
                'author_id' => $user->id,
                'is_published' => true,
            ]);
            expect($response->status())->toBe(403);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Blog Acknowledge Action
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog Acknowledge Action', function () {
        it('attaches acknowledgement once (idempotent)', function () {
            $user = User::factory()->create();
            $blog = Blog::factory()->create();

            Auth::login($user);

            AcknowledgeBlog::run($blog); // 1st time
            AcknowledgeBlog::run($blog); // 2nd time -> no duplicate

            $this->assertDatabaseHas('blog_author', [
                'author_id' => $user->id,
                'blog_id' => $blog->id,
            ]);
            $this->assertDatabaseCount('blog_author', 1);
        });

        it('rejects guests for acknowledgement', function () {
            $blog = Blog::factory()->create();
            AcknowledgeBlog::run($blog);
        })->throws(ValidationException::class);
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Blog acknowledgers relation
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog acknowledgers relation', function () {
        it('relates a blog to users who acknowledged it', function () {
            $blog = Blog::factory()->create();
            $user = User::factory()->create();

            $blog->acknowledgers()->syncWithoutDetaching([$user->id]);

            expect($blog->acknowledgers)->toHaveCount(1);
            expect($blog->acknowledgers->first()->id)->toBe($user->id);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Blog index filters & pagination
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog index filters & pagination', function () {
        it('filters by category and tag', function () {
            $cat = Category::factory()->create();
            $tag = Tag::factory()->create();
            $b1 = Blog::factory()->create();
            $b2 = Blog::factory()->create();

            $b1->categories()->sync([$cat->id]);
            $b1->tags()->sync([$tag->id]);

            // Adjust endpoints/params if your controller names differ
            $this->get("/blogs?category={$cat->id}")
                ->assertStatus(200)
                ->assertSee($b1->title)
                ->assertDontSee($b2->title);

            $this->get("/blogs?tag={$tag->id}")
                ->assertStatus(200)
                ->assertSee($b1->title)
                ->assertDontSee($b2->title);
        });

        it('filters by search term', function () {
            Blog::factory()->create(['title' => 'Laravel Tips']);
            Blog::factory()->create(['title' => 'Minecraft Tricks']);

            // Adjust endpoint if needed (e.g., '/api/blogs?search=Laravel')
            $res = $this->get('/blogs?search=Laravel');

            $res->assertStatus(200);
            $res->assertSee('Laravel Tips');
            $res->assertDontSee('Minecraft Tricks');
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Blog Pivot Integrity
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog Pivot Integrity', function () {
        it('enforces unique (author_id, blog_id) on acknowledged_blogs', function () {
            $u = User::factory()->create();
            $b = Blog::factory()->create();

            DB::table('acknowledged_blogs')->insert([
                'author_id' => $u->id,
                'blog_id' => $b->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->expectException(QueryException::class);

            DB::table('acknowledged_blogs')->insert([
                'author_id' => $u->id,
                'blog_id' => $b->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Blog Policies
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog Policies', function () {
        it('allows acknowledge when policy says so', function () {
            $user = User::factory()->create();
            $blog = Blog::factory()->create();

            expect(Gate::forUser($user)->allows('acknowledge', $blog))
                ->toBeTrue();
        });

        it('prevents non-admin from deleting via policy', function () {
            $user = User::factory()->create();
            $blog = Blog::factory()->create();

            expect(Gate::forUser($user)->denies('delete', $blog))
                ->toBeTrue();
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Blog route model binding (slug)
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog route model binding (slug)', function () {
        it('resolves blog by slug', function () {
            $blog = Blog::factory()->create(['slug' => 'my-post']);

            // If route name is different, adjust accordingly or use URL directly
            $res = $this->get('/blogs/my-post');
            $res->assertOk()->assertSee($blog->title);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Blog validation (HTTP)
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog validation (HTTP)', function () {
        it('requires a title on store', function () {
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin);

            // Web form (session errors)
            $res = $this->post('/blogs', ['title' => '', 'content' => 'x']);
            $res->assertStatus(302)->assertSessionHasErrors(['title']);
        });

        it('requires unique title on store', function () {
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin);

            Blog::factory()->create(['title' => 'Unique Blog']);

            // Web form (session errors)
            $res = $this->post('/blogs', ['title' => 'Unique Blog', 'content' => 'x']);
            $res->assertStatus(302)->assertSessionHasErrors(['title']);

            // API variant:
            $res = $this->postJson('/blogs', ['title' => 'Unique Blog', 'content' => 'x']);
            $res->assertStatus(422)->assertJsonValidationErrors(['title']);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete all blogs', function () {
            Blog::factory()->count(5)->create();
            expect(Blog::count())->toBe(5);
            Blog::truncate();
            expect(Blog::count())->toBe(0);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a blog', function () {
            $author = User::factory()->create();
            $category = Category::factory()->create();
            $blog = Blog::factory()->create([
                'author_id' => $author->id,
                'category_id' => $category->id,
            ]);
            expect($blog)->toBeInstanceOf(Blog::class);
            expect($blog->author_id)->toBe($author->id);
            expect($blog->category_id)->toBe($category->id);
        });

        it('can delete a blog', function () {
            $blog = Blog::factory()->create();
            $blog->delete();
            expect(Blog::find($blog->id))->toBeNull();
        });

        it('can update a blog', function () {
            $blog = Blog::factory()->create();
            $blog->update(['title' => 'Updated Title']);
            expect($blog->fresh()->title)->toBe('Updated Title');
        });

        it('can view a blog', function () {
            $blog = Blog::factory()->create();
            $found = Blog::find($blog->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($blog->id);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {

        // Categories
        describe('Categories', function () {
            it('can associate and retrieve a single category for a blog', function () {
                $category = Category::factory()->create();
                $blog = Blog::factory()->create();
                $blog->categories()->attach($category->id);
                expect($blog->categories()->count())->toBe(1);
                expect($blog->categories->first()->id)->toBe($category->id);
            });

            it('can associate and retrieve multiple categories for a blog', function () {
                $categories = Category::factory()->count(3)->create();
                $blog = Blog::factory()->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(3);
            });

            it('can detach all categories from a blog', function () {
                $categories = Category::factory()->count(3)->create();
                $blog = Blog::factory()->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                $blog->categories()->detach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(0);
            });

            it('can detach a single category from a blog', function () {
                $category = Category::factory()->create();
                $blog = Blog::factory()->create();
                $blog->categories()->attach($category->id);
                $blog->categories()->detach($category->id);
                expect($blog->categories()->count())->toBe(0);
            });
        });

        // Comments
        describe('Comments', function () {
            it('can associate and retrieve a single comment for a blog', function () {
                $blog = Blog::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                expect($blog->comments()->count())->toBe(1);
                expect($blog->comments->first()->id)->toBe($comment->id);
            });

            it('can associate and retrieve multiple comments for a blog', function () {
                $blog = Blog::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                expect($blog->comments()->count())->toBe(3);
            });

            it('can delete all comments from a blog', function () {
                $blog = Blog::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                foreach ($blog->comments as $comment) {
                    $comment->delete();
                }
                expect($blog->comments()->count())->toBe(0);
            });

            it('can delete a single comment from a blog', function () {
                $blog = Blog::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                $comment->delete();
                expect($blog->comments()->count())->toBe(0);
            });
        });

        // Tags
        describe('Tags', function () {
            it('can attach and retrieve a single tag for a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                expect($blog->tags()->count())->toBe(1);
                expect($blog->tags->first()->id)->toBe($tag->id);
            });

            it('can attach and retrieve multiple tags for a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(3);
            });

            it('cannot attach a non-existent tag', function () {
                $blog = Blog::factory()->create();
                expect(fn () => $blog->tags()->attach(999999))->toThrow(QueryException::class);
            });

            it('can detach all tags from a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                $blog->tags()->detach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(0);
            });

            it('can detach a single tag from a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                $blog->tags()->detach($tag->id);
                expect($blog->tags()->count())->toBe(0);
            });
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Events
    // ───────────────────────────────────────────────────────────────────────────
    describe('Events', function () {
        it('fires created event when blog is made', function () {
            $called = false;
            Blog::created(function () use (&$called) {
                $called = true;
            });
            Blog::factory()->create();
            expect($called)->toBeTrue();
        });

        it('fires deleted event when blog is deleted', function () {
            $called = false;
            Blog::deleted(function () use (&$called) {
                $called = true;
            });
            $blog = Blog::factory()->create();
            $blog->delete();
            expect($called)->toBeTrue();
        });

        it('fires updated event when blog is updated', function () {
            $called = false;
            Blog::updated(function () use (&$called) {
                $called = true;
            });
            $blog = Blog::factory()->create();
            $blog->update(['title' => 'Updated Title']);
            expect($called)->toBeTrue();
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Livewire Blogs list (conditional)
    // ───────────────────────────────────────────────────────────────────────────
    if (class_exists(Livewire::class)) {
        describe('Livewire Blogs list', function () {
            it('renders and searches', function () {
                Blog::factory()->create(['title' => 'Laravel Tips']);
                Blog::factory()->create(['title' => 'Minecraft Tricks']);

                Livewire::test('blogs.index') // update alias if needed
                    ->assertSee('Laravel Tips')
                    ->assertSee('Minecraft Tricks')
                    ->set('search', 'Laravel')
                    ->assertSee('Laravel Tips')
                    ->assertDontSee('Minecraft Tricks');
            });
        });
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create blogs with accented characters in the title', function () {
            $blog = Blog::factory()->create(['title' => 'Café']);
            expect($blog->title)->toBe('Café');
        });

        it('can create blogs with emoji', function () {
            $blog = Blog::factory()->create(['title' => '🔥']);
            expect($blog->title)->toBe('🔥');
        });

        it('can create blogs with non-English titles', function () {
            $blog = Blog::factory()->create(['title' => 'ブログ']);
            expect($blog->title)->toBe('ブログ');
        });

        it('can create blogs with titles containing numbers', function () {
            $blog = Blog::factory()->create(['title' => 'Blog Title 123']);
            expect($blog->title)->toBe('Blog Title 123');
        });

        it('can create blogs with special characters in the title', function () {
            $blog = Blog::factory()->create(['title' => '!@#$%^&*()']);
            expect($blog->title)->toBe('!@#$%^&*()');
        });

        it('can create blogs with titles containing HTML tags', function () {
            $blog = Blog::factory()->create(['title' => '<strong>Bold Title</strong>']);
            expect($blog->title)->toBe('<strong>Bold Title</strong>');
        });

        it('can create blogs with titles that are hyperlinked', function () {
            $blog = Blog::factory()->create(['title' => '<a href="#">Blog Title</a>']);
            expect($blog->title)->toBe('<a href="#">Blog Title</a>');
        });

        it('can create blogs with titles containing Markdown', function () {
            $blog = Blog::factory()->create(['title' => '**Bold Title**']);
            expect($blog->title)->toBe('**Bold Title**');
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Performance
    // ───────────────────────────────────────────────────────────────────────────
    describe('Performance', function () {
        it('can bulk attach many tags to a blog efficiently', function () {
            $blog = Blog::factory()->create();
            $tags = Tag::factory()->count(50)->create();
            $blog->tags()->attach($tags->pluck('id')->toArray());
            expect($blog->tags()->count())->toBe(50);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Restoration
    // ───────────────────────────────────────────────────────────────────────────
    describe('Restoration', function () {
        it('can restore soft deleted blogs', function () {
            $blog = Blog::factory()->create();
            $blog->delete();
            $blog->restore();
            $found = Blog::query()->find($blog->id);
            expect($found)->not->toBeNull();
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Security
    // ───────────────────────────────────────────────────────────────────────────
    describe('Security', function () {
        it('prevents unauthorized user from accessing a blog', function () {
            $blog = Blog::factory()->create();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->get('/blogs/'.$blog->id);
            expect($response->status())->toBe(403);
        });

        it('prevents unauthorized user from creating a blog', function () {
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->post('/blogs', ['title' => 'New Blog', 'content' => 'Blog content']);
            expect($response->status())->toBe(403);
        });

        it('prevents unauthorized user from deleting a blog', function () {
            $blog = Blog::factory()->create();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->delete('/blogs/'.$blog->id);
            expect($response->status())->toBe(403);
        });

        it('prevents unauthorized user from updating a blog', function () {
            $blog = Blog::factory()->create();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->put('/blogs/'.$blog->id, ['title' => 'Updated Title']);
            expect($response->status())->toBe(403);
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Soft Deletes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Soft Deletes', function () {
        it('does not return soft deleted blogs in queries', function () {
            $blog = Blog::factory()->create();
            $blog->delete();
            $found = Blog::query()->find($blog->id);
            expect($found)->toBeNull();
        });
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Validation (model-style placeholders)
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a title', function () {
            $blog = Blog::factory()->create(['title' => '']);
            expect($blog->isValid())->toBeFalse();
            expect($blog->getErrors())->toContain('The title field is required.');
        });

        it('requires a unique title', function () {
            $blog1 = Blog::factory()->count(3)->create(['title' => 'Unique Blog']);
            $blog2 = Blog::factory()->count(3)->create(['title' => 'Unique Blog']);
            $blog1->each(function ($blog) {
                expect($blog->isValid())->toBeFalse();
                expect($blog->getErrors())->toContain('The title field must be unique.');
            });
            $blog2->each(function ($blog) {
                expect($blog->isValid())->toBeFalse();
                expect($blog->getErrors())->toContain('The title field must be unique.');
            });
        });
    });
});
