<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag Model', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // CRUD Operations
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a tag', function () {
            $tag = Tag::factory()->create(['name' => 'Test Tag']);
            expect($tag)->toBeInstanceOf(Tag::class);
            expect($tag->name)->toBe('Test Tag');
        })->done(assignee: 'ghostridr');

        it('can retrieve a tag by id', function () {
            $tag = Tag::factory()->create();
            $found = Tag::find($tag->id);
            expect($found)->not()->toBeNull();
            expect($found->id)->toBe($tag->id);
        })->done(assignee: 'ghostridr');

        it('can update a tag', function () {
            $tag = Tag::factory()->create();
            $tag->update(['name' => 'Updated Tag']);
            expect($tag->fresh()->name)->toBe('Updated Tag');
        })->done(assignee: 'ghostridr');

        it('can delete a tag', function () {
            $tag = Tag::factory()->create();
            $tag->delete();
            expect(Tag::find($tag->id))->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete Tags', function () {
            $tags = Tag::factory()->count(5)->create();
            foreach ($tags as $tag) {
                $tag->delete();
            }
            foreach ($tags as $tag) {
                expect(Tag::find($tag->id))->toBeNull();
            }
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {
        it('cannot create a tag with an empty name', function () {
            $tag = new Tag(['name' => '']);
            expect($tag->isValid())->toBeFalse();
            expect($tag->getErrors())->toContain('The name field is required.');
        })->done(assignee: 'ghostridr');

        it('cannot create a tag with a duplicate name', function () {
            Tag::factory()->create(['name' => 'Unique Tag']);
            $tag = new Tag(['name' => 'Unique Tag']);
            expect($tag->isValid())->toBeFalse();
            expect($tag->getErrors())->toContain('The name field must be unique.');
        })->done(assignee: 'ghostridr');

        it('cannot attach a non-existent tag', function () {
            $announcement = Announcement::factory()->create();
            expect(fn () => $announcement->tags()->attach(999999))->toThrow(QueryException::class);

            $blog = Blog::factory()->create();
            expect(fn () => $blog->tags()->attach(999999))->toThrow(QueryException::class);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Events & Observers
    // ───────────────────────────────────────────────────────────────────────────
    describe('Events', function () {
        it('fires created event when Tag is made', function () {
            $called = false;
            Tag::created(function () use (&$called) {
                $called = true;
            });
            Tag::factory()->create();
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create Tags with non-English names', function () {
            $Tag = Tag::factory()->create(['name' => 'タグ']);
            expect($Tag->name)->toBe('タグ');
        })->done(assignee: 'ghostridr');

        it('can create Tags with emoji', function () {
            $Tag = Tag::factory()->create(['name' => '🔥']);
            expect($Tag->name)->toBe('🔥');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Performance
    // ───────────────────────────────────────────────────────────────────────────
    describe('Performance', function () {
        it('can bulk attach many Tags to a blog efficiently', function () {
            $blog = Blog::factory()->create();
            $tags = Tag::factory()->count(50)->create();
            $blog->tags()->attach($tags->pluck('id')->toArray());
            expect($blog->tags()->count())->toBe(50);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Relationships
    // ───────────────────────────────────────────────────────────────────────────
    describe('Tag Relationships', function () {
        // Announcement relationships
        describe('Announcements', function () {
            it('can associate and retrieve a single tag for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tag = Tag::factory()->create();
                $announcement->tags()->attach($tag->id);
                expect($announcement->tags()->count())->toBe(1);
                expect($announcement->tags->first()->id)->toBe($tag->id);
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple tags for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach a single tag from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tag = Tag::factory()->create();
                $announcement->tags()->attach($tag->id);
                $announcement->tags()->detach($tag->id);
                expect($announcement->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach all tags from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                $announcement->tags()->detach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('cannot attach a non-existent tag to a blog', function () {
                $blog = Blog::factory()->create();
                expect(fn () => $blog->tags()->attach(999999))->toThrow(QueryException::class);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // Blog relationships
        describe('Blogs', function () {
            it('can associate and retrieve a single tag for a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                expect($blog->tags()->count())->toBe(1);
                expect($blog->tags->first()->id)->toBe($tag->id);
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple tags for a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach a single tag from a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                $blog->tags()->detach($tag->id);
                expect($blog->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach all tags from a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                $blog->tags()->detach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('cannot attach a non-existent tag to an announcement', function () {
                $announcement = Announcement::factory()->create();
                expect(fn () => $announcement->tags()->attach(999999))->toThrow(QueryException::class);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // User relationships
        describe('Users', function () {
            it('can associate and retrieve a user for a tag', function () {
                $author = User::factory()->create();
                $tag = Tag::factory()->create(['author_id' => $author->id]);
                expect($tag->author)->toBeInstanceOf(User::class);
                expect($tag->author->id)->toBe($author->id);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Soft Deletes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Soft Deletes', function () {
        it('does not return soft deleted Tags in queries', function () {
            $Tag = Tag::factory()->create();
            $Tag->delete();
            $found = Tag::query()->find($Tag->id);
            expect($found)->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // User Relationships
    // ───────────────────────────────────────────────────────────────────────────
    describe('Users', function () {
        it('can associate and retrieve a user for a tag', function () {
            $author = User::factory()->create();
            $tag = Tag::factory()->create(['author_id' => $author->id]);
            expect($tag->author)->toBeInstanceOf(User::class);
            expect($tag->author->id)->toBe($author->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation Rules
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a name', function () {
            $tag = new Tag(['name' => '']);
            expect($tag->isValid())->toBeFalse();
            expect($tag->getErrors())->toContain('The name field is required.');
        })->done(assignee: 'ghostridr');

        it('requires a unique name', function () {
            Tag::factory()->create(['name' => 'Unique Tag']);
            $tag2 = Tag::factory()->make(['name' => 'Unique Tag']);
            expect($tag2->isValid())->toBeFalse();
            expect($tag2->getErrors())->toContain('The name field must be unique.');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
