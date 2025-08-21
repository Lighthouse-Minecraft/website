<?php

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('Announcement Model', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // Accessors & Helpers
    // ───────────────────────────────────────────────────────────────────────────
    describe('Accessors & Helpers', function () {
        it('authorName returns the author name or fallback', function () {
            $user = User::factory()->create(['name' => 'Alice']);
            $announcement = Announcement::factory()->create(['author_id' => $user->id]);
            expect($announcement->authorName())->toBe('Alice');

            $announcementWithoutAuthor = Announcement::factory()->create(['author_id' => null]);
            expect($announcementWithoutAuthor->authorName())->toBe('Unknown Author');
        })->done(assignee: 'ghostridr');

        it('excerpt returns first lines for plain text and html', function () {
            $plain = Announcement::factory()->create(['content' => "Line 1\nLine 2\nLine 3\nLine 4"]);
            expect($plain->excerpt(3))->toBe("Line 1\nLine 2\nLine 3\n...");

            $html = Announcement::factory()->withRichContent()->create();
            $excerpt = $html->excerpt(3);
            expect($excerpt)->toContain('...');
            expect(explode("\n", $excerpt))->toHaveCount(4); // 3 lines + ellipsis
        })->done(assignee: 'ghostridr');

        it('isAuthoredBy checks author id match', function () {
            $user = User::factory()->create();
            $announcement = Announcement::factory()->create(['author_id' => $user->id]);
            expect($announcement->isAuthoredBy($user))->toBeTrue();

            $other = User::factory()->create();
            expect($announcement->isAuthoredBy($other))->toBeFalse();
        })->done(assignee: 'ghostridr');

        it('publicationDate formats published_at', function () {
            $date = Carbon::create(2024, 5, 10, 12, 0, 0);
            $announcement = Announcement::factory()->create(['published_at' => $date]);
            expect($announcement->publicationDate())->toBe('May 10, 2024');
        })->done(assignee: 'ghostridr');

        it('route returns the show URL', function () {
            $announcement = Announcement::factory()->create();
            $url = $announcement->route();
            expect($url)->toContain('/announcements/');
            expect($url)->toEndWith((string) $announcement->id);
        })->done(assignee: 'ghostridr');

        it('tagsAsString and categoriesAsString return comma separated names', function () {
            $announcement = Announcement::factory()->create();
            $tags = Tag::factory()->count(2)->create();
            $cats = Category::factory()->count(2)->create();
            $announcement->tags()->attach($tags->pluck('id')->all());
            $announcement->categories()->attach($cats->pluck('id')->all());

            expect($announcement->tagsAsString())->toBe($tags->pluck('name')->implode(', '));
            expect($announcement->categoriesAsString())->toBe($cats->pluck('name')->implode(', '));
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Casts
    // ───────────────────────────────────────────────────────────────────────────
    describe('Casts', function () {
        it('casts is_published to bool and published_at to datetime', function () {
            $announcement = Announcement::factory()->create(['is_published' => 1]);
            expect($announcement->is_published)->toBeBool();
            expect($announcement->published_at)->toBeInstanceOf(Carbon::class);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete announcements', function () {
            $announcements = Announcement::factory()->count(5)->create();
            foreach ($announcements as $announcement) {
                $announcement->delete();
            }
            foreach ($announcements as $announcement) {
                expect(Announcement::find($announcement->id))->toBeNull();
            }
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create an announcement', function () {
            $author = User::factory()->create();
            $announcement = Announcement::factory()->create([
                'author_id' => $author->id,
            ]);
            // Attach a category via relationship (many-to-many)
            $category = Category::factory()->create();
            $announcement->categories()->attach($category->id);

            expect($announcement)->toBeInstanceOf(Announcement::class);
            expect($announcement->author_id)->toBe($author->id);
            expect($announcement->categories()->count())->toBe(1);
        })->done(assignee: 'ghostridr');

        it('can delete an announcement', function () {
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            expect(Announcement::find($announcement->id))->toBeNull();
        })->done(assignee: 'ghostridr');

        it('can update an announcement', function () {
            $announcement = Announcement::factory()->create();
            $announcement->update(['title' => 'Updated Title']);
            expect($announcement->fresh()->title)->toBe('Updated Title');
        })->done(assignee: 'ghostridr');

        it('can view an announcement', function () {
            $announcement = Announcement::factory()->create();
            $found = Announcement::find($announcement->id);
            expect($found)->not()->toBeNull();
            expect($found->id)->toBe($announcement->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Eager Loading
    // ───────────────────────────────────────────────────────────────────────────
    describe('Eager Loading', function () {
        it('loads default relations via $with on retrieval', function () {
            $announcement = Announcement::factory()->create();
            $model = Announcement::query()->find($announcement->id);
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
            it('can associate and retrieve a single category for an announcement', function () {
                $category = Category::factory()->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($category->id);
                expect($announcement->categories()->count())->toBe(1);
                expect($announcement->categories->first()->id)->toBe($category->id);
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple categories for an announcement', function () {
                $categories = Category::factory()->count(3)->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach all categories from an announcement', function () {
                $categories = Category::factory()->count(3)->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                $announcement->categories()->detach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach a single category from an announcement', function () {
                $category = Category::factory()->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($category->id);
                $announcement->categories()->detach($category->id);
                expect($announcement->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // Comments
        describe('Comments', function () {
            it('can associate and retrieve a single comment for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                expect($announcement->comments()->count())->toBe(1);
                expect($announcement->comments->first()->id)->toBe($comment->id);
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple comments for an announcement', function () {
                $announcement = Announcement::factory()->create();
                Comment::factory()->count(3)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                expect($announcement->comments()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can delete all comments from an announcement', function () {
                $announcement = Announcement::factory()->create();
                Comment::factory()->count(3)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                foreach ($announcement->comments as $comment) {
                    $comment->delete();
                }
                expect($announcement->comments()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can delete a single comment from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                $comment->delete();
                expect($announcement->comments()->count())->toBe(0);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // Tags
        describe('Tags', function () {
            it('can attach and retrieve a single tag for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tag = Tag::factory()->create();
                $announcement->tags()->attach($tag->id);
                expect($announcement->tags()->count())->toBe(1);
                expect($announcement->tags->first()->id)->toBe($tag->id);
            })->done(assignee: 'ghostridr');

            it('can attach and retrieve multiple tags for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach all tags from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                $announcement->tags()->detach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach a single tag from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tag = Tag::factory()->create();
                $announcement->tags()->attach($tag->id);
                $announcement->tags()->detach($tag->id);
                expect($announcement->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create announcements with accented characters in the title', function () {
            $announcement = Announcement::factory()->create(['title' => 'Café']);
            expect($announcement->title)->toBe('Café');
        })->done(assignee: 'ghostridr');

        it('can create announcements with emoji', function () {
            $announcement = Announcement::factory()->create(['title' => '🔥']);
            expect($announcement->title)->toBe('🔥');
        })->done(assignee: 'ghostridr');

        it('can create announcements with titles containing HTML tags', function () {
            $announcement = Announcement::factory()->create(['title' => '<strong>Bold Title</strong>']);
            expect($announcement->title)->toBe('<strong>Bold Title</strong>');
        })->done(assignee: 'ghostridr');

        it('can create announcements with titles containing Markdown', function () {
            $announcement = Announcement::factory()->create(['title' => '**Bold Title**']);
            expect($announcement->title)->toBe('**Bold Title**');
        })->done(assignee: 'ghostridr');

        it('can create announcements with non-English titles', function () {
            $announcement = Announcement::factory()->create(['title' => 'ブログ']);
            expect($announcement->title)->toBe('ブログ');
        })->done(assignee: 'ghostridr');

        it('can create announcements with titles containing numbers', function () {
            $announcement = Announcement::factory()->create(['title' => 'Announcement Title 123']);
            expect($announcement->title)->toBe('Announcement Title 123');
        })->done(assignee: 'ghostridr');

        it('can create announcements with special characters in the title', function () {
            $announcement = Announcement::factory()->create(['title' => '!@#$%^&*()']);
            expect($announcement->title)->toBe('!@#$%^&*()');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Relations
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement acknowledgers relation', function () {
        it('relates an announcement to users who acknowledged it', function () {
            $announcement = Announcement::factory()->create();
            $user = User::factory()->create();

            $announcement->acknowledgers()->syncWithoutDetaching([$user->id]);

            expect($announcement->acknowledgers)->toHaveCount(1);
            expect($announcement->acknowledgers->first()->id)->toBe($user->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Restoration
    // ───────────────────────────────────────────────────────────────────────────
    describe('Restoration', function () {
        it('can restore soft deleted announcements', function () {
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            $announcement->restore();
            $found = Announcement::query()->find($announcement->id);
            expect($found)->not()->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Scopes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Scopes', function () {
        it('byAuthor returns only announcements by the given author', function () {
            $author = User::factory()->create();
            Announcement::factory()->count(2)->byAuthor($author)->create();
            Announcement::factory()->count(1)->create();

            $results = Announcement::query()->byAuthor($author->id)->get();
            expect($results)->toHaveCount(2);
            expect($results->pluck('author_id')->unique()->first())->toBe($author->id);
        })->done(assignee: 'ghostridr');

        it('published returns only published announcements', function () {
            Announcement::factory()->count(2)->published()->create();
            Announcement::factory()->count(3)->unpublished()->create();

            $results = Announcement::query()->published()->get();
            expect($results)->toHaveCount(2);
            expect($results->every(fn ($a) => $a->is_published === true))->toBeTrue();
        })->done(assignee: 'ghostridr');

        it('publishedAt filters by date threshold', function () {
            $early = Carbon::now()->subDays(10)->floorSecond();
            $mid = Carbon::now()->subDays(5)->floorSecond();
            $late = Carbon::now()->subDay()->floorSecond();
            Announcement::factory()->create(['published_at' => $early]);
            Announcement::factory()->create(['published_at' => $mid]);
            Announcement::factory()->create(['published_at' => $late]);

            $results = Announcement::query()->publishedAt($mid)->get();
            $min = $results->pluck('published_at')->min();
            expect($min->copy()->floorSecond()->getTimestamp())->toBeGreaterThanOrEqual($mid->getTimestamp());
            expect($results)->toHaveCount(2);
        })->done(assignee: 'ghostridr');

        it('withCategory filters by related category name', function () {
            $catA = Category::factory()->create(['name' => 'Alpha']);
            $catB = Category::factory()->create(['name' => 'Beta']);

            $a1 = Announcement::factory()->create();
            $a1->categories()->attach($catA->id);
            $a2 = Announcement::factory()->create();
            $a2->categories()->attach($catB->id);

            $results = Announcement::query()->withCategory('Alpha')->get();
            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($a1->id);
        })->done(assignee: 'ghostridr');

        it('withTag filters by related tag name', function () {
            $tagA = Tag::factory()->create(['name' => 'X']);
            $tagB = Tag::factory()->create(['name' => 'Y']);

            $a1 = Announcement::factory()->create();
            $a1->tags()->attach($tagA->id);
            $a2 = Announcement::factory()->create();
            $a2->tags()->attach($tagB->id);

            $results = Announcement::query()->withTag('X')->get();
            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($a1->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Soft Deletes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Soft Deletes', function () {
        it('does not return soft deleted announcements in queries', function () {
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            $found = Announcement::query()->find($announcement->id);
            expect($found)->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Totals & Counts
    // ───────────────────────────────────────────────────────────────────────────
    describe('Totals & Counts', function () {
        it('counts related categories, tags, and comments correctly', function () {
            $announcement = Announcement::factory()->create();
            $announcement->categories()->attach(Category::factory()->count(2)->create()->pluck('id')->all());
            $announcement->tags()->attach(Tag::factory()->count(3)->create()->pluck('id')->all());
            Comment::factory()->count(4)->create([
                'commentable_id' => $announcement->id,
                'commentable_type' => Announcement::class,
            ]);

            expect($announcement->categoriesCount())->toBe(2);
            expect($announcement->tagsCount())->toBe(3);
            expect($announcement->commentsCount())->toBe(4);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('isValid returns false when title is missing and provides an error', function () {
            $announcement = new Announcement;

            expect($announcement->isValid())->toBeFalse();
            $errors = $announcement->getErrors();
            expect($errors)->toHaveKey('title');
            expect($errors['title'])->toBe('The title field is required.');
        })->done(assignee: 'ghostridr');

        it('isValid returns false when title is not unique and provides an error', function () {
            $existing = Announcement::factory()->create(['title' => 'Duplicate']);
            $announcement = new Announcement(['title' => $existing->title]);

            expect($announcement->isValid())->toBeFalse();
            $errors = $announcement->getErrors();
            expect($errors)->toHaveKey('title');
            expect($errors['title'])->toBe('The title field must be unique.');
        })->done(assignee: 'ghostridr');

        it('isValid returns true when title is present and unique', function () {
            Announcement::factory()->create(['title' => 'Existing']);
            $announcement = new Announcement(['title' => 'New Unique Title']);

            expect($announcement->isValid())->toBeTrue();
            expect($announcement->getErrors())->toBeArray()->toBeEmpty();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
});
