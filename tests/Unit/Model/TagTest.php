<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
// API/Resource describe
describe('API', function () {
    it('can list tags via API', function () {
        Tag::factory()->count(3)->create();
        $response = $this->getJson('/api/tags');
        expect($response->json())->toHaveCount(3);
    })->todo('');

    // Announcements relationships
    describe('Announcements', function () {
        it('can associate and retrieve a single tag for an announcement', function () {
            $announcement = Announcement::factory()->create();
            $tag = Tag::factory()->create();
            $announcement->tags()->attach($tag->id);
            expect($announcement->tags()->count())->toBe(1);
            expect($announcement->tags->first()->id)->toBe($tag->id);
        })->todo('');

        it('can associate and retrieve multiple tags for an announcement', function () {
            $announcement = Announcement::factory()->create();
            $tags = Tag::factory()->count(3)->create();
            $announcement->tags()->attach($tags->pluck('id')->toArray());
            expect($announcement->tags()->count())->toBe(3);
        })->todo('');

        it('can detach a single tag from an announcement', function () {
            $announcement = Announcement::factory()->create();
            $tag = Tag::factory()->create();
            $announcement->tags()->attach($tag->id);
            $announcement->tags()->detach($tag->id);
            expect($announcement->tags()->count())->toBe(0);
        })->todo('');

        it('can detach all tags from an announcement', function () {
            $announcement = Announcement::factory()->create();
            $tags = Tag::factory()->count(3)->create();
            $announcement->tags()->attach($tags->pluck('id')->toArray());
            $announcement->tags()->detach($tags->pluck('id')->toArray());
            expect($announcement->tags()->count())->toBe(0);
        })->todo('');
    })->todo('');

    // Authorization for tag actions
    describe('Authorization', function () {
        it('allows admin to create a tag', function () {
            $this->actingAsAdmin();
            $response = $this->post('/tags', ['name' => 'New Tag']);
            expect($response->status())->toBe(201);
        })->todo('');

        it('prevents non-admin from creating a tag', function () {
            $this->actingAsUser();
            $response = $this->post('/tags', ['name' => 'New Tag']);
            expect($response->status())->toBe(403);
        })->todo('');
    })->todo('');

    // Basic CRUD operations
    describe('CRUD', function () {
        it('can create a tag', function () {
            $tag = Tag::factory()->create(['name' => 'Test Tag']);
            expect($tag)->toBeInstanceOf(Tag::class);
            expect($tag->name)->toBe('Test Tag');
        })->todo('');

        it('can view a tag', function () {
            $tag = Tag::factory()->create();
            $found = Tag::find($tag->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($tag->id);
        })->todo('');

        it('can update a tag', function () {
            $tag = Tag::factory()->create();
            $tag->update(['name' => 'Updated Tag']);
            expect($tag->fresh()->name)->toBe('Updated Tag');
        })->todo('');

        it('can delete a tag', function () {
            $tag = Tag::factory()->create();
            $tag->delete();
            expect(Tag::find($tag->id))->toBeNull();
        })->todo('');
    })->todo('');

    // Blog relationships
    describe('Blogs', function () {
        it('can associate and retrieve a single tag for a blog', function () {
            $blog = Blog::factory()->create();
            $tag = Tag::factory()->create();
            $blog->tags()->attach($tag->id);
            expect($blog->tags()->count())->toBe(1);
            expect($blog->tags->first()->id)->toBe($tag->id);
        })->todo('');

        it('can associate and retrieve multiple tags for a blog', function () {
            $blog = Blog::factory()->create();
            $tags = Tag::factory()->count(3)->create();
            $blog->tags()->attach($tags->pluck('id')->toArray());
            expect($blog->tags()->count())->toBe(3);
        })->todo('');

        it('can detach a single tag from a blog', function () {
            $blog = Blog::factory()->create();
            $tag = Tag::factory()->create();
            $blog->tags()->attach($tag->id);
            $blog->tags()->detach($tag->id);
            expect($blog->tags()->count())->toBe(0);
        })->todo('');

        it('can detach all tags from a blog', function () {
            $blog = Blog::factory()->create();
            $tags = Tag::factory()->count(3)->create();
            $blog->tags()->attach($tags->pluck('id')->toArray());
            $blog->tags()->detach($tags->pluck('id')->toArray());
            expect($blog->tags()->count())->toBe(0);
        })->todo('');
    })->todo('');

    // Cleanup and bulk actions
    describe('Cleanup', function () {
        it('can delete all tags', function () {
            Tag::factory()->count(5)->create();
            expect(Tag::count())->toBe(5);
            Tag::truncate();
            expect(Tag::count())->toBe(0);
        })->todo('');
    })->todo('');

    // Edge cases
    describe('Edge Cases', function () {
        it('cannot attach a non-existent category', function () {
            $announcement = Announcement::factory()->create();
            $announcement->categories()->attach(999999); // unlikely to exist
            expect($announcement->categories()->count())->toBe(0);

            $blog = Blog::factory()->create();
            $blog->categories()->attach(999999); // unlikely to exist
            expect($blog->categories()->count())->toBe(0);
        })->todo('');
    })->todo('');

    // Events & observers
    describe('Events', function () {
        it('fires created event when tag is made', function () {
            $called = false;
            Tag::created(function () use (&$called) {
                $called = true;
            });
            Tag::factory()->create();
            expect($called)->toBeTrue();
        })->todo('');
    })->todo('');

    // Localization/Internationalization
    describe('Localization', function () {
        it('can create tags with non-English names', function () {
            $tag = Tag::factory()->create(['name' => 'タグ']);
            expect($tag->name)->toBe('タグ');
        })->todo('');
        it('can create tags with emoji', function () {
            $tag = Tag::factory()->create(['name' => '🔥']);
            expect($tag->name)->toBe('🔥');
        })->todo('');
    })->todo('');

    // Performance
    describe('Performance', function () {
        it('can bulk attach many tags to a blog efficiently', function () {
            $blog = Blog::factory()->create();
            $tags = Tag::factory()->count(50)->create();
            $blog->tags()->attach($tags->pluck('id')->toArray());
            expect($blog->tags()->count())->toBe(50);
        })->todo('');
    })->todo('');

    // Security
    describe('Security', function () {
        it('prevents unauthorized user from deleting a tag', function () {
            $tag = Tag::factory()->create();
            $this->actingAsUser();
            $response = $this->delete('/tags/'.$tag->id);
            expect($response->status())->toBe(403);
        })->todo('');
    })->todo('');

    // Soft deletes
    describe('Soft Deletes', function () {
        it('does not return soft deleted tags in queries', function () {
            $tag = Tag::factory()->create();
            $tag->delete();
            $found = Tag::query()->find($tag->id);
            expect($found)->toBeNull();
        })->todo('');
    })->todo('');

    // User relationships
    describe('Users', function () {
        it('can associate and retrieve a user for a tag', function () {
            $author = User::factory()->create();
            $tag = Tag::factory()->create(['author_id' => $author->id]);
            expect($tag->author)->toBeInstanceOf(User::class);
            expect($tag->author->id)->toBe($author->id);
        })->todo('');
    })->todo('');

    // Validation rules for tags
    describe('Validation', function () {
        it('requires a name', function () {
            $tag = new Tag(['name' => '']);
            expect($tag->isValid())->toBeFalse();
            expect($tag->getErrors())->toContain('The name field is required.');
        })->todo('');

        it('requires a unique name', function () {
            Tag::factory()->create(['name' => 'Unique Tag']);
            $tag2 = Tag::factory()->make(['name' => 'Unique Tag']);
            expect($tag2->isValid())->toBeFalse();
            expect($tag2->getErrors())->toContain('The name field must be unique.');
        })->todo('');
    })->todo('');
})->todo('');
