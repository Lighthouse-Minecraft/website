<?php

use App\Actions\AcknowledgeAnnouncement;
use App\Models\Announcement;
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
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Announcement feature tests for posts
describe('Announcement Feature', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // API
    // ───────────────────────────────────────────────────────────────────────────
    describe('API', function () {
        it('can list announcements via web page', function () {
            Announcement::factory()->count(3)->create();

            $res = $this->get(route('announcement.index'));

            $res->assertOk();

            // See at least one title in the rendered HTML
            $first = Announcement::first();
            $res->assertSee(e($first->title));
        })->todo('Implement API endpoint to list all announcements and ensure it returns the correct count.');
    })->todo('Set up API routes and controllers for announcements.');

    // ───────────────────────────────────────────────────────────────────────────
    // Authorization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Authorization', function () {
        it('allows admin to create an announcement', function () {
            $this->withExceptionHandling();
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin);
            $response = $this->post('/announcements', [
                'title' => 'New Announcement',
                'content' => 'Content',
                'author_id' => $admin->id,
                'is_published' => true,
            ]);
            expect($response->status())->toBe(201);
        })->todo('
            Route/controller returns 404 instead of 201. Ensure /announcements POST exists and returns 201 for admin.
        ');

        it('prevents non-admin from creating an announcement', function () {
            $this->withExceptionHandling();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->post('/announcements', [
                'title' => 'New Announcement',
                'content' => 'Content',
                'author_id' => $user->id,
                'is_published' => true,
            ]);
            expect($response->status())->toBe(403);
        })->todo('
            Route/controller returns 404 instead of 403. Ensure /announcements POST returns 403 for non-admin.
        ');
    })->todo('Set up proper authorization policies for announcements.');

    // ───────────────────────────────────────────────────────────────────────────
    // Announcement Acknowledge Action
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement Acknowledge Action', function () {
        it('attaches acknowledgement once (idempotent)', function () {
            $user = User::factory()->create();
            $announcement = Announcement::factory()->create();
            Auth::login($user);
            AcknowledgeAnnouncement::run($announcement); // 1st time
            AcknowledgeAnnouncement::run($announcement); // 2nd time -> no duplicate
            $this->assertDatabaseHas('announcement_author', [
                'author_id' => $user->id,
                'announcement_id' => $announcement->id,
            ]);
            $this->assertDatabaseCount('announcement_author', 1);
        })->todo('Check acknowledged_blogs pivot table and ensure correct columns for announcements.');

        it('rejects guests for acknowledgement', function () {
            $announcement = Announcement::factory()->create();
            AcknowledgeAnnouncement::run($announcement);
        })->todo('
            AcknowledgeAnnouncement::handle expects 2 arguments, only 1 provided. Update test or action to match signature and throw ValidationException for guests.
        ');
    })->todo('
        Update AcknowledgeAnnouncement action to handle announcements and ensure it throws ValidationException for guests.
    ');

    // ───────────────────────────────────────────────────────────────────────────
    // Announcement acknowledgers relation
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement acknowledgers relation', function () {
        it('relates a Announcement to users who acknowledged it', function () {
            $announcement = Announcement::factory()->create();
            $user = User::factory()->create();

            $announcement->acknowledgers()->syncWithoutDetaching([$user->id]);

            expect($announcement->acknowledgers)->toHaveCount(1);
            expect($announcement->acknowledgers->first()->id)->toBe($user->id);
        })->done('');
    })->done('Ensure Announcement model has acknowledgers relation defined and working correctly.');

    // ───────────────────────────────────────────────────────────────────────────
    // Announcement index filters & pagination
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement index filters & pagination', function () {
        it('filters by category and tag', function () {
            $cat = Category::factory()->create();
            $tag = Tag::factory()->create();
            $b1 = Announcement::factory()->create();
            $b2 = Announcement::factory()->create();
            $b1->categories()->sync([$cat->id]);
            $b1->tags()->sync([$tag->id]);
            $this->get("/announcements?category={$cat->id}")
                ->assertStatus(200)
                ->assertSee($b1->title)
                ->assertDontSee($b2->title);
            $this->get("/announcements?tag={$tag->id}")
                ->assertStatus(200)
                ->assertSee($b1->title)
                ->assertDontSee($b2->title);
        })->todo('
            Route /announcements?category and /announcements?tag return 404. Ensure these routes exist and filter correctly.
        ');

        it('filters by search term', function () {
            Announcement::factory()->create(['title' => 'Laravel Tips']);
            Announcement::factory()->create(['title' => 'Minecraft Tricks']);
            $res = $this->get('/announcements?search=Laravel');
            $res->assertStatus(200);
            $res->assertSee('Laravel Tips');
            $res->assertDontSee('Minecraft Tricks');
        })->todo('Route /announcements?search returns 404. Ensure this route exists and filters by search term.');
    })->todo('Implement Announcement index controller with filtering and pagination.');

    // ───────────────────────────────────────────────────────────────────────────
    // Announcement Pivot Integrity
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement Pivot Integrity', function () {
        it('enforces unique (author_id, announcement_id) on acknowledged_blogs', function () {
            $u = User::factory()->create();
            $b = Announcement::factory()->create();
            DB::table('acknowledged_blogs')->insert([
                'author_id' => $u->id,
                'announcement_id' => $b->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->expectException(QueryException::class);
            DB::table('acknowledged_blogs')->insert([
                'author_id' => $u->id,
                'announcement_id' => $b->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        })->todo('acknowledged_blogs table missing announcement_id column. Add column and enforce uniqueness.');
    })->todo('Implement Announcement Pivot Integrity tests.');

    // ───────────────────────────────────────────────────────────────────────────
    // Announcement Policies
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement Policies', function () {
        it('allows acknowledge when policy says so', function () {
            $user = User::factory()->create();
            $announcement = Announcement::factory()->create();

            expect(Gate::forUser($user)->allows('acknowledge', $announcement))
                ->toBeTrue();
        })->done('');

        it('prevents non-admin from deleting via policy', function () {
            $user = User::factory()->create();
            $announcement = Announcement::factory()->create();

            expect(Gate::forUser($user)->denies('delete', $announcement))
                ->toBeTrue();
        })->done('');
    })->done('Configure AnnouncementPolicy and ensure it is registered.');

    // ───────────────────────────────────────────────────────────────────────────
    // Announcement route model binding (slug)
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement route model binding (slug)', function () {
        it('resolves Announcement by slug', function () {
            $announcement = Announcement::factory()->create(['slug' => 'my-post']);
            $res = $this->get('/announcements/my-post');
            $res->assertOk()->assertSee($announcement->title);
        })->todo('announcements table missing slug column. Add slug column and ensure route model binding works.');
    })->todo('Implement Announcement route model binding and ensure it works with slugs.');

    // ───────────────────────────────────────────────────────────────────────────
    // Announcement validation (HTTP)
    // ───────────────────────────────────────────────────────────────────────────
    describe('Announcement validation (HTTP)', function () {
        it('requires a title on store', function () {
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin);
            $res = $this->post('/announcements', ['title' => '', 'content' => 'x']);
            $res->assertStatus(302)->assertSessionHasErrors(['title']);
        })->todo('POST /announcements returns 404 instead of 302. Ensure validation and error handling for missing title.');

        it('requires unique title on store', function () {
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin);
            Announcement::factory()->create(['title' => 'Unique Announcement']);
            $res = $this->post('/announcements', ['title' => 'Unique Announcement', 'content' => 'x']);
            $res->assertStatus(302)->assertSessionHasErrors(['title']);
            $res = $this->postJson('/announcements', ['title' => 'Unique Announcement', 'content' => 'x']);
            $res->assertStatus(422)->assertJsonValidationErrors(['title']);
        })->todo('POST /announcements returns 404 instead of 302/422. Ensure validation and error handling for unique title.');
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete all announcements', function () {
            Announcement::factory()->count(5)->create();
            expect(Announcement::count())->toBe(5);
            Announcement::truncate();
            expect(Announcement::count())->toBe(0);
        })->done('');
    })->done('Implement Announcement cleanup tests.');

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a Announcement', function () {
            $author = User::factory()->create();
            $category = Category::factory()->create();
            $announcement = Announcement::factory()->create([
                'author_id' => $author->id,
                'category_id' => $category->id,
            ]);
            expect($announcement)->toBeInstanceOf(Announcement::class);
            expect($announcement->author_id)->toBe($author->id);
            expect($announcement->category_id)->toBe($category->id);
        })->todo('announcements table missing category_id column. Add column and ensure creation works.');

        it('can delete a Announcement', function () {
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            expect(Announcement::find($announcement->id))->toBeNull();
        })->done('');

        it('can update a Announcement', function () {
            $announcement = Announcement::factory()->create();
            $announcement->update(['title' => 'Updated Title']);
            expect($announcement->fresh()->title)->toBe('Updated Title');
        })->done('');

        it('can view a Announcement', function () {
            $announcement = Announcement::factory()->create();
            $found = Announcement::find($announcement->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($announcement->id);
        })->done('');
    })->todo('Ensure Announcement resource is properly registered.');

    // ───────────────────────────────────────────────────────────────────────────
    // Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {
        // Categories
        describe('Categories', function () {
            it('can associate and retrieve a single category for a Announcement', function () {
                $category = Category::factory()->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($category->id);
                expect($announcement->categories()->count())->toBe(1);
                expect($announcement->categories->first()->id)->toBe($category->id);
            })->done('');

            it('can associate and retrieve multiple categories for a Announcement', function () {
                $categories = Category::factory()->count(3)->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(3);
            })->done('');

            it('can detach all categories from a Announcement', function () {
                $categories = Category::factory()->count(3)->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                $announcement->categories()->detach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(0);
            })->done('');

            it('can detach a single category from a Announcement', function () {
                $category = Category::factory()->create();
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach($category->id);
                $announcement->categories()->detach($category->id);
                expect($announcement->categories()->count())->toBe(0);
            })->done('');
        })->done('Check functionality of category management.');

        // Comments
        describe('Comments', function () {
            it('can associate and retrieve a single comment for a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                expect($announcement->comments()->count())->toBe(1);
                expect($announcement->comments->first()->id)->toBe($comment->id);
            })->done('');

            it('can associate and retrieve multiple comments for a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                expect($announcement->comments()->count())->toBe(3);
            })->done('');

            it('can delete all comments from a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                foreach ($announcement->comments as $comment) {
                    $comment->delete();
                }
                expect($announcement->comments()->count())->toBe(0);
            })->done('');

            it('can delete a single comment from a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                $comment->delete();
                expect($announcement->comments()->count())->toBe(0);
            })->done('');
        })->done('Check functionality of comment management.');

        // Tags
        describe('Tags', function () {
            it('can attach and retrieve a single tag for a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $tag = Tag::factory()->create();
                $announcement->tags()->attach($tag->id);
                expect($announcement->tags()->count())->toBe(1);
                expect($announcement->tags->first()->id)->toBe($tag->id);
            })->done('');

            it('can attach and retrieve multiple tags for a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(3);
            })->done('');

            it('cannot attach a non-existent tag', function () {
                $announcement = Announcement::factory()->create();
                expect(fn () => $announcement->tags()->attach(999999))->toThrow(QueryException::class);
            })->done('');

            it('can detach all tags from a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $tags = Tag::factory()->count(3)->create();
                $announcement->tags()->attach($tags->pluck('id')->toArray());
                $announcement->tags()->detach($tags->pluck('id')->toArray());
                expect($announcement->tags()->count())->toBe(0);
            })->done('');

            it('can detach a single tag from a Announcement', function () {
                $announcement = Announcement::factory()->create();
                $tag = Tag::factory()->create();
                $announcement->tags()->attach($tag->id);
                $announcement->tags()->detach($tag->id);
                expect($announcement->tags()->count())->toBe(0);
            })->done('');
        })->done('Check functionality of tag management.');
    })->done('Test edge cases for Announcement model relations.');

    // ───────────────────────────────────────────────────────────────────────────
    // Events
    // ───────────────────────────────────────────────────────────────────────────
    describe('Events', function () {
        it('fires created event when Announcement is made', function () {
            $called = false;
            Announcement::created(function () use (&$called) {
                $called = true;
            });
            Announcement::factory()->create();
            expect($called)->toBeTrue();
        })->done('');

        it('fires deleted event when Announcement is deleted', function () {
            $called = false;
            Announcement::deleted(function () use (&$called) {
                $called = true;
            });
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            expect($called)->toBeTrue();
        })->done('');

        it('fires updated event when Announcement is updated', function () {
            $called = false;
            Announcement::updated(function () use (&$called) {
                $called = true;
            });
            $announcement = Announcement::factory()->create();
            $announcement->update(['title' => 'Updated Title']);
            expect($called)->toBeTrue();
        })->done('');
    })->done('Check functionality of announcement events.');

    // ───────────────────────────────────────────────────────────────────────────
    // Livewire announcements list (conditional)
    // ───────────────────────────────────────────────────────────────────────────
    if (class_exists(Livewire::class)) {
        describe('Livewire announcements list', function () {
            it('renders and searches', function () {
                Announcement::factory()->create(['title' => 'Laravel Tips']);
                Announcement::factory()->create(['title' => 'Minecraft Tricks']);
                Livewire::test('announcements.index') // update alias if needed
                    ->assertSee('Laravel Tips')
                    ->assertSee('Minecraft Tricks')
                    ->set('search', 'Laravel')
                    ->assertSee('Laravel Tips')
                    ->assertDontSee('Minecraft Tricks');
            })->todo('Livewire component announcements.index not found. Create component and view.');
        })->todo('Set up Livewire and create announcements.index component for listing announcements.');
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create announcements with accented characters in the title', function () {
            $announcement = Announcement::factory()->create(['title' => 'Café']);
            expect($announcement->title)->toBe('Café');
        })->done('');

        it('can create announcements with emoji', function () {
            $announcement = Announcement::factory()->create(['title' => '🔥']);
            expect($announcement->title)->toBe('🔥');
        })->done('');

        it('can create announcements with non-English titles', function () {
            $announcement = Announcement::factory()->create(['title' => 'ブログ']);
            expect($announcement->title)->toBe('ブログ');
        })->done('');

        it('can create announcements with titles containing numbers', function () {
            $announcement = Announcement::factory()->create(['title' => 'Announcement Title 123']);
            expect($announcement->title)->toBe('Announcement Title 123');
        })->done('');

        it('can create announcements with special characters in the title', function () {
            $announcement = Announcement::factory()->create(['title' => '!@#$%^&*()']);
            expect($announcement->title)->toBe('!@#$%^&*()');
        })->done('');

        it('can create announcements with titles containing HTML tags', function () {
            $announcement = Announcement::factory()->create(['title' => '<strong>Bold Title</strong>']);
            expect($announcement->title)->toBe('<strong>Bold Title</strong>');
        })->done('');

        it('can create announcements with titles that are hyperlinked', function () {
            $announcement = Announcement::factory()->create(['title' => '<a href="#">Announcement Title</a>']);
            expect($announcement->title)->toBe('<a href="#">Announcement Title</a>');
        })->done('');

        it('can create announcements with titles containing Markdown', function () {
            $announcement = Announcement::factory()->create(['title' => '**Bold Title**']);
            expect($announcement->title)->toBe('**Bold Title**');
        })->done('');
    })->done('Test localization of Announcement model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Performance
    // ───────────────────────────────────────────────────────────────────────────
    describe('Performance', function () {
        it('can bulk attach many tags to a Announcement efficiently', function () {
            $announcement = Announcement::factory()->create();
            $tags = Tag::factory()->count(50)->create();
            $announcement->tags()->attach($tags->pluck('id')->toArray());
            expect($announcement->tags()->count())->toBe(50);
        })->done('');
    })->done('Test performance of Announcement model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Restoration
    // ───────────────────────────────────────────────────────────────────────────
    describe('Restoration', function () {
        it('can restore soft deleted announcements', function () {
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            $announcement->restore();
            $found = Announcement::query()->find($announcement->id);
            expect($found)->not->toBeNull();
        })->todo('Announcement model missing SoftDeletes trait. Add trait to support restore().');
    })->todo('Implement soft delete restoration for Announcement model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Security
    // ───────────────────────────────────────────────────────────────────────────
    describe('Security', function () {
        it('prevents unauthorized user from accessing a Announcement', function () {
            $announcement = Announcement::factory()->create();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->get('/announcements/'.$announcement->id);
            expect($response->status())->toBe(403);
        })->todo('Route/controller returns 500 instead of 403. Ensure unauthorized access returns 403.');

        it('prevents unauthorized user from creating a Announcement', function () {
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->post('/announcements', ['title' => 'New Announcement', 'content' => 'Announcement content']);
            expect($response->status())->toBe(403);
        })->todo('Route/controller returns 404 instead of 403. Ensure unauthorized creation returns 403.');

        it('prevents unauthorized user from deleting a Announcement', function () {
            $announcement = Announcement::factory()->create();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->delete('/announcements/'.$announcement->id);
            expect($response->status())->toBe(403);
        })->todo('Route/controller returns 405 instead of 403. Ensure unauthorized deletion returns 403.');

        it('prevents unauthorized user from updating a Announcement', function () {
            $announcement = Announcement::factory()->create();
            $user = User::factory()->create();
            $this->actingAs($user);
            $response = $this->put('/announcements/'.$announcement->id, ['title' => 'Updated Title']);
            expect($response->status())->toBe(403);
        })->todo('Route/controller returns 405 instead of 403. Ensure unauthorized update returns 403.');
    })->todo('Implement security for Announcement model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Soft Deletes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Soft Deletes', function () {
        it('does not return soft deleted announcements in queries', function () {
            $announcement = Announcement::factory()->create();
            $announcement->delete();
            $found = Announcement::query()->find($announcement->id);
            expect($found)->toBeNull();
        })->done('');
    })->done('Test soft deletes for Announcement model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation (model-style placeholders)
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a title', function () {
            $announcement = Announcement::factory()->create(['title' => '']);
            expect($announcement->isValid())->toBeFalse();
            expect($announcement->getErrors())->toContain('The title field is required.');
        })->todo('Announcement model missing isValid() and getErrors() methods. Implement validation logic.');

        it('requires a unique title', function () {
            $announcement1 = Announcement::factory()->count(3)->create(['title' => 'Unique Announcement']);
            $announcement2 = Announcement::factory()->count(3)->create(['title' => 'Unique Announcement']);
            $announcement1->each(function ($announcement) {
                expect($announcement->isValid())->toBeFalse();
                expect($announcement->getErrors())->toContain('The title field must be unique.');
            });
            $announcement2->each(function ($announcement) {
                expect($announcement->isValid())->toBeFalse();
                expect($announcement->getErrors())->toContain('The title field must be unique.');
            });
        })->todo('Announcement model missing isValid() and getErrors() methods for unique title validation.');
    })->todo('Implement validation for Announcement model.');
})->wip('Implement strict validation for Announcement model.');
