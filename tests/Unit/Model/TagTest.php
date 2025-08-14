<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Feature', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // API
    // ───────────────────────────────────────────────────────────────────────────
    describe('API', function () {
        it('can list tags via API', function () {
            Tag::factory()->count(3)->create();
            $response = $this->getJson('/api/tags');
            expect($response->json())->toHaveCount(3);
        })->todo('Implement API endpoint to list all tags and ensure it returns the correct count.');
    })->todo('Implement API resource tests for Tag model.');

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
            })->todo('Ensure Announcement model can associate and retrieve a single tag via many-to-many relationship.');

            it('can associate and retrieve multiple tags for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(3);
            })->todo('Ensure Announcement model can associate and retrieve multiple tags via many-to-many relationship.');

            it('can detach a single tag from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tag = Tag::factory()->create();
                $announcement->tags()->attach($tag->id);
                $announcement->tags()->detach($tag->id);
                expect($announcement->tags()->count())->toBe(0);
            })->todo('Test detaching a single tag from an announcement and verify the relationship is removed.');

            it('can detach all tags from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                $announcement->tags()->detach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(0);
            })->todo('Test detaching all tags from an announcement and verify no tags remain associated.');
        })->todo('Test many-to-many relationships and tag management for Announcement model.');

        // Blog relationships
        describe('Blogs', function () {
            it('can associate and retrieve a single tag for a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                expect($blog->tags()->count())->toBe(1);
                expect($blog->tags->first()->id)->toBe($tag->id);
            })->todo('Ensure Blog model can associate and retrieve a single tag via many-to-many relationship.');

            it('can associate and retrieve multiple tags for a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(3);
            })->todo('Ensure Blog model can associate and retrieve multiple tags via many-to-many relationship.');

            it('can detach a single tag from a blog', function () {
                $blog = Blog::factory()->create();
                $tag = Tag::factory()->create();
                $blog->tags()->attach($tag->id);
                $blog->tags()->detach($tag->id);
                expect($blog->tags()->count())->toBe(0);
            })->todo('Test detaching a single tag from a blog and verify the relationship is removed.');

            it('can detach all tags from a blog', function () {
                $blog = Blog::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $blog->tags()->attach($tags->pluck('id')->toArray());
                $blog->tags()->detach($tags->pluck('id')->toArray());
                expect($blog->tags()->count())->toBe(0);
            })->todo('Test detaching all tags from a blog and verify no tags remain associated.');
        })->todo('Test many-to-many relationships and tag management for Blog model.');

        // User relationships
        describe('Users', function () {
            it('can associate and retrieve a user for a tag', function () {
                $author = User::factory()->create();
                $tag = Tag::factory()->create(['author_id' => $author->id]);
                expect($tag->author)->toBeInstanceOf(User::class);
                expect($tag->author->id)->toBe($author->id);
            })->todo('Ensure Tag model has an author relationship and can retrieve the associated user.');
        });
    })->todo('Implement all relationship tests for Tag model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Authorization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Authorization', function () {
        it('allows admin to create a tag', function () {
            $this->actingAsAdmin();
            $response = $this->post('/tags', ['name' => 'New Tag']);
            expect($response->status())->toBe(201);
        })->todo('Test that an admin user can create a tag via the API.');

        it('prevents non-admin from creating a tag', function () {
            $this->actingAsUser();
            $response = $this->post('/tags', ['name' => 'New Tag']);
            expect($response->status())->toBe(403);
        })->todo('Test that a non-admin user cannot create a tag via the API.');
    })->todo('Test authorization rules for tag creation.');

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD Operations
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a tag', function () {
            $tag = Tag::factory()->create(['name' => 'Test Tag']);
            expect($tag)->toBeInstanceOf(Tag::class);
            expect($tag->name)->toBe('Test Tag');
        })->todo('Test tag creation and verify the tag is persisted with correct attributes.');

        it('can view a tag', function () {
            $tag = Tag::factory()->create();
            $found = Tag::find($tag->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($tag->id);
        })->todo('Test retrieving a tag by ID and verify its attributes.');

        it('can update a tag', function () {
            $tag = Tag::factory()->create();
            $tag->update(['name' => 'Updated Tag']);
            expect($tag->fresh()->name)->toBe('Updated Tag');
        })->todo('Test updating a tag’s name and verify the change is persisted.');

        it('can delete a tag', function () {
            $tag = Tag::factory()->create();
            $tag->delete();
            expect(Tag::find($tag->id))->toBeNull();
        })->todo('Test deleting a tag and verify it is removed from the database.');
    })->todo('Test basic CRUD operations for Tag model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete all tags', function () {
            Tag::factory()->count(5)->create();
            expect(Tag::count())->toBe(5);
            Tag::truncate();
            expect(Tag::count())->toBe(0);
        })->todo('Test bulk deletion of tags and verify all are removed from the database.');
    })->todo('Test bulk deletion and cleanup actions for Tag model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {
        it('cannot attach a non-existent category', function () {
            $announcement = Announcement::factory()->create();
            $announcement->categories()->attach(999999); // unlikely to exist
            expect($announcement->categories()->count())->toBe(0);

            $blog = Blog::factory()->create();
            $blog->categories()->attach(999999); // unlikely to exist
            expect($blog->categories()->count())->toBe(0);
        })->todo('Test that attaching a non-existent category to an announcement or blog does not create a relationship.');
    })->todo('Test edge cases for tag relationships and validation.');

    // ───────────────────────────────────────────────────────────────────────────
    // Events & Observers
    // ───────────────────────────────────────────────────────────────────────────
    describe('Events', function () {
        it('fires created event when tag is made', function () {
            $called = false;
            Tag::created(function () use (&$called) {
                $called = true;
            });
            Tag::factory()->create();
            expect($called)->toBeTrue();
        })->todo('Test that the created event is fired when a tag is created.');
    })->todo('Test event and observer logic for Tag model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create tags with non-English names', function () {
            $tag = Tag::factory()->create(['name' => 'タグ']);
            expect($tag->name)->toBe('タグ');
        })->todo('Test tag creation with non-English names and verify correct storage.');

        it('can create tags with emoji', function () {
            $tag = Tag::factory()->create(['name' => '🔥']);
            expect($tag->name)->toBe('🔥');
        })->todo('Test tag creation with emoji and verify correct storage.');
    })->todo('Test localization and internationalization for Tag names.');

    // Performance
    describe('Performance', function () {
        it('can bulk attach many tags to a blog efficiently', function () {
            $blog = Blog::factory()->create();
            $tags = Tag::factory()->count(50)->create();
            $blog->tags()->attach($tags->pluck('id')->toArray());
            expect($blog->tags()->count())->toBe(50);
        })->todo('Test bulk attaching many tags to a blog and verify performance and correctness.');
    })->todo('Test performance for bulk tag operations.');

    // Security
    describe('Security', function () {
        it('prevents unauthorized user from deleting a tag', function () {
            $tag = Tag::factory()->create();
            $this->actingAsUser();
            $response = $this->delete('/tags/'.$tag->id);
            expect($response->status())->toBe(403);
        })->todo('Test that a non-admin user cannot delete a tag via the API.');
    })->todo('Test security and authorization for tag deletion.');

    // Soft deletes
    describe('Soft Deletes', function () {
        it('does not return soft deleted tags in queries', function () {
            $tag = Tag::factory()->create();
            $tag->delete();
            $found = Tag::query()->find($tag->id);
            expect($found)->toBeNull();
        })->todo('Test that soft deleted tags are not returned in queries.');
    })->todo('Test soft delete behavior for Tag model.');

    // User relationships
    describe('Users', function () {
        it('can associate and retrieve a user for a tag', function () {
            $author = User::factory()->create();
            $tag = Tag::factory()->create(['author_id' => $author->id]);
            expect($tag->author)->toBeInstanceOf(User::class);
            expect($tag->author->id)->toBe($author->id);
        })->todo('Ensure Tag model has an author relationship and can retrieve the associated user.');
    })->todo('Test author relationship and user association for Tag model.');

    // Validation rules for tags
    describe('Validation', function () {
        it('requires a name', function () {
            $tag = new Tag(['name' => '']);
            expect($tag->isValid())->toBeFalse();
            expect($tag->getErrors())->toContain('The name field is required.');
        })->todo('Implement validation logic in Tag model to require a non-empty name and return errors if missing.');

        it('requires a unique name', function () {
            Tag::factory()->create(['name' => 'Unique Tag']);
            $tag2 = Tag::factory()->make(['name' => 'Unique Tag']);
            expect($tag2->isValid())->toBeFalse();
            expect($tag2->getErrors())->toContain('The name field must be unique.');
        })->todo('Implement validation logic in Tag model to require a unique name and return errors if duplicate.');
    })->todo('Test validation rules for Tag model.');
})->todo('Implement all Tag feature tests.');
