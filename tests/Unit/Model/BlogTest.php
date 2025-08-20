<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('Blog Model', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // Accessors & Helpers
    // ───────────────────────────────────────────────────────────────────────────
    describe('Accessors & Helpers', function () {
        it('authorName returns the author name or fallback', function () {
            $user = User::factory()->create(['name' => 'Alice']);
            $blog = Blog::factory()->create(['author_id' => $user->id]);
            expect($blog->authorName())->toBe('Alice');

            // Simulate missing relation without violating NOT NULL constraint
            $blog->setRelation('author', null);
            expect($blog->authorName())->toBe('Unknown Author');
        })->done(assignee: 'ghostridr');

        it('excerpt returns first lines for plain text and html', function () {
            $plain = Blog::factory()->create(['content' => "Line 1\nLine 2\nLine 3\nLine 4"]);
            expect($plain->excerpt(3))->toBe("Line 1\nLine 2\nLine 3\n...");

            $html = Blog::factory()->withRichContent()->create();
            $excerpt = $html->excerpt(3);
            expect($excerpt)->toContain('...');
            expect(explode("\n", $excerpt))->toHaveCount(4);
        })->done(assignee: 'ghostridr');

        it('isAuthoredBy checks author id match', function () {
            $user = User::factory()->create();
            $blog = Blog::factory()->create(['author_id' => $user->id]);
            expect($blog->isAuthoredBy($user))->toBeTrue();

            $other = User::factory()->create();
            expect($blog->isAuthoredBy($other))->toBeFalse();
        })->done(assignee: 'ghostridr');

        it('publicationDate formats published_at', function () {
            $date = Carbon::create(2024, 5, 10, 12, 0, 0);
            $blog = Blog::factory()->create(['published_at' => $date]);
            expect($blog->publicationDate())->toBe('May 10, 2024');
        })->done(assignee: 'ghostridr');

        it('route returns the show URL', function () {
            $blog = Blog::factory()->create();
            $url = $blog->route();
            expect($url)->toContain('/blogs/');
            expect($url)->toEndWith((string) $blog->id);
        })->done(assignee: 'ghostridr');

        it('tagsAsString and categoriesAsString return comma separated names', function () {
            $blog = Blog::factory()->create();
            $tags = Tag::factory()->count(2)->create();
            $cats = Category::factory()->count(2)->create();
            $blog->tags()->attach($tags->pluck('id')->all());
            $blog->categories()->attach($cats->pluck('id')->all());

            expect($blog->tagsAsString())->toBe($tags->pluck('name')->implode(', '));
            expect($blog->categoriesAsString())->toBe($cats->pluck('name')->implode(', '));
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Casts
    // ───────────────────────────────────────────────────────────────────────────
    describe('Casts', function () {
        it('casts booleans and datetime correctly', function () {
            $blog = Blog::factory()->create([
                'is_published' => 1,
                'is_public' => 1,
                'published_at' => Carbon::now(),
            ]);
            expect($blog->is_published)->toBeBool();
            expect($blog->is_public)->toBeBool();
            expect($blog->published_at)->toBeInstanceOf(Carbon::class);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete blogs', function () {
            $blogs = Blog::factory()->count(5)->create();
            foreach ($blogs as $blog) {
                $blog->delete();
            }
            foreach ($blogs as $blog) {
                expect(Blog::find($blog->id))->toBeNull();
            }
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a blog', function () {
            $author = User::factory()->create();
            $blog = Blog::factory()->create([
                'author_id' => $author->id,
            ]);
            $category = Category::factory()->create();
            $blog->categories()->attach($category->id);

            expect($blog)->toBeInstanceOf(Blog::class);
            expect($blog->author_id)->toBe($author->id);
            expect($blog->categories()->count())->toBe(1);
        })->done(assignee: 'ghostridr');

        it('can delete a blog', function () {
            $blog = Blog::factory()->create();
            $blog->delete();
            expect(Blog::find($blog->id))->toBeNull();
        })->done(assignee: 'ghostridr');

        it('can update a blog', function () {
            $blog = Blog::factory()->create();
            $blog->update(['title' => 'Updated Title']);
            expect($blog->fresh()->title)->toBe('Updated Title');
        })->done(assignee: 'ghostridr');

        it('can view a blog', function () {
            $blog = Blog::factory()->create();
            $found = Blog::find($blog->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($blog->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Eager Loading
    // ───────────────────────────────────────────────────────────────────────────
    describe('Eager Loading', function () {
        it('loads default relations via $with on retrieval', function () {
            $blog = Blog::factory()->create();
            $model = Blog::query()->find($blog->id);
            expect($model->relationLoaded('author'))->toBeTrue();
            expect($model->relationLoaded('comments'))->toBeTrue();
            expect($model->relationLoaded('tags'))->toBeTrue();
            expect($model->relationLoaded('categories'))->toBeTrue();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

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
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple categories for a blog', function () {
                $categories = Category::factory()->count(3)->create();
                $blog = Blog::factory()->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach all categories from a blog', function () {
                $categories = Category::factory()->count(3)->create();
                $blog = Blog::factory()->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                $blog->categories()->detach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach a single category from a blog', function () {
                $category = Category::factory()->create();
                $blog = Blog::factory()->create();
                $blog->categories()->attach($category->id);
                $blog->categories()->detach($category->id);
                expect($blog->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

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
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple comments for a blog', function () {
                $blog = Blog::factory()->create();
                Comment::factory()->count(3)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                expect($blog->comments()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can delete all comments from a blog', function () {
                $blog = Blog::factory()->create();
                Comment::factory()->count(3)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                foreach ($blog->comments as $comment) {
                    $comment->delete();
                }
                expect($blog->comments()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can delete a single comment from a blog', function () {
                $blog = Blog::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                $comment->delete();
                expect($blog->comments()->count())->toBe(0);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // Tags
        describe('Tags', function () {
            it('can attach and retrieve a single tag for a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                expect($blog->tags()->count())->toBe(1);
                expect($blog->tags->first()->id)->toBe($tag->id);
            })->done(assignee: 'ghostridr');

            it('can attach and retrieve multiple tags for a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach all tags from a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                $blog->tags()->detach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach a single tag from a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                $blog->tags()->detach($tag->id);
                expect($blog->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create blogs with accented characters in the title', function () {
            $blog = Blog::factory()->create(['title' => 'Café']);
            expect($blog->title)->toBe('Café');
        })->done(assignee: 'ghostridr');

        it('can create blogs with emoji', function () {
            $blog = Blog::factory()->create(['title' => '🔥']);
            expect($blog->title)->toBe('🔥');
        })->done(assignee: 'ghostridr');

        it('can create blogs with non-English titles', function () {
            $blog = Blog::factory()->create(['title' => 'ブログ']);
            expect($blog->title)->toBe('ブログ');
        })->done(assignee: 'ghostridr');

        it('can create blogs with titles containing HTML tags', function () {
            $blog = Blog::factory()->create(['title' => '<strong>Bold Title</strong>']);
            expect($blog->title)->toBe('<strong>Bold Title</strong>');
        })->done(assignee: 'ghostridr');

        it('can create blogs with titles containing Markdown', function () {
            $blog = Blog::factory()->create(['title' => '**Bold Title**']);
            expect($blog->title)->toBe('**Bold Title**');
        })->done(assignee: 'ghostridr');

        it('can create blogs with titles containing numbers', function () {
            $blog = Blog::factory()->create(['title' => 'Blog Title 123']);
            expect($blog->title)->toBe('Blog Title 123');
        })->done(assignee: 'ghostridr');

        it('can create blogs with special characters in the title', function () {
            $blog = Blog::factory()->create(['title' => '!@#$%^&*()']);
            expect($blog->title)->toBe('!@#$%^&*()');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Relations
    // ───────────────────────────────────────────────────────────────────────────
    describe('Blog acknowledgers relation', function () {
        it('relates a blog to users who acknowledged it', function () {
            $blog = Blog::factory()->create();
            $user = User::factory()->create();

            $blog->acknowledgers()->syncWithoutDetaching([$user->id]);

            expect($blog->acknowledgers)->toHaveCount(1);
            expect($blog->acknowledgers->first()->id)->toBe($user->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

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
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Scopes
    // ───────────────────────────────────────────────────────────
    describe('Scopes', function () {
        it('byAuthor returns only blogs by the given author', function () {
            $author = User::factory()->create();
            Blog::factory()->count(2)->byAuthor($author)->create();
            Blog::factory()->count(1)->create();

            $results = Blog::query()->byAuthor($author->id)->get();
            expect($results)->toHaveCount(2);
            expect($results->pluck('author_id')->unique()->first())->toBe($author->id);
        })->done(assignee: 'ghostridr');

        it('public returns only public blogs', function () {
            Blog::factory()->count(2)->create(['is_public' => true]);
            Blog::factory()->count(3)->create(['is_public' => false]);

            $results = Blog::query()->public()->get();
            expect($results)->toHaveCount(2);
            expect($results->every(fn ($b) => $b->is_public === true))->toBeTrue();
        })->done(assignee: 'ghostridr');

        it('published returns only published blogs', function () {
            Blog::factory()->count(2)->published()->create();
            Blog::factory()->count(3)->unpublished()->create();

            $results = Blog::query()->published()->get();
            expect($results)->toHaveCount(2);
            expect($results->every(fn ($b) => $b->is_published === true))->toBeTrue();
        })->done(assignee: 'ghostridr');

        it('publishedAt filters by date threshold', function () {
            $early = Carbon::now()->subDays(10)->floorSecond();
            $mid = Carbon::now()->subDays(5)->floorSecond();
            $late = Carbon::now()->subDay()->floorSecond();
            Blog::factory()->create(['published_at' => $early]);
            Blog::factory()->create(['published_at' => $mid]);
            Blog::factory()->create(['published_at' => $late]);

            $results = Blog::query()->publishedAt($mid)->get();
            $min = $results->pluck('published_at')->min();
            expect($min->copy()->floorSecond()->getTimestamp())->toBeGreaterThanOrEqual($mid->getTimestamp());
            expect($results)->toHaveCount(2);
        })->done(assignee: 'ghostridr');

        it('withCategory filters by related category name', function () {
            $catA = Category::factory()->create(['name' => 'Alpha']);
            $catB = Category::factory()->create(['name' => 'Beta']);

            $b1 = Blog::factory()->create();
            $b1->categories()->attach($catA->id);
            $b2 = Blog::factory()->create();
            $b2->categories()->attach($catB->id);

            $results = Blog::query()->withCategory('Alpha')->get();
            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($b1->id);
        })->done(assignee: 'ghostridr');

        it('withTag filters by related tag name', function () {
            $tagA = Tag::factory()->create(['name' => 'X']);
            $tagB = Tag::factory()->create(['name' => 'Y']);

            $b1 = Blog::factory()->create();
            $b1->tags()->attach($tagA->id);
            $b2 = Blog::factory()->create();
            $b2->tags()->attach($tagB->id);

            $results = Blog::query()->withTag('X')->get();
            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($b1->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Soft Deletes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Soft Deletes', function () {
        it('does not return soft deleted blogs in queries', function () {
            $blog = Blog::factory()->create();
            $blog->delete();
            $found = Blog::query()->find($blog->id);
            expect($found)->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Totals & Counts
    // ───────────────────────────────────────────────────────────────────────────
    describe('Totals & Counts', function () {
        it('counts related categories, tags, and comments correctly', function () {
            $blog = Blog::factory()->create();
            $blog->categories()->attach(Category::factory()->count(2)->create()->pluck('id')->all());
            $blog->tags()->attach(Tag::factory()->count(3)->create()->pluck('id')->all());
            Comment::factory()->count(4)->create([
                'commentable_id' => $blog->id,
                'commentable_type' => Blog::class,
            ]);

            expect($blog->categoriesCount())->toBe(2);
            expect($blog->tagsCount())->toBe(3);
            expect($blog->commentsCount())->toBe(4);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a title', function () {
            $blog = Blog::factory()->create(['title' => '']);
            expect($blog->isValid())->toBeFalse();
            expect($blog->getErrors())->toContain('The title field is required.');
        })->done(assignee: 'ghostridr');

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
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
