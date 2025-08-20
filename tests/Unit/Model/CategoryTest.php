<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Category model tests only
describe('Category Model', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // CRUD Operations
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a category', function () {
            $category = Category::factory()->create(['name' => 'Test Category']);
            expect($category)->toBeInstanceOf(Category::class);
            expect($category->name)->toBe('Test Category');
        })->done(assignee: 'ghostridr');

        it('can update a category', function () {
            $category = Category::factory()->create();
            $category->update(['name' => 'Updated Category']);
            expect($category->fresh()->name)->toBe('Updated Category');
        })->done(assignee: 'ghostridr');

        it('can delete a category', function () {
            $category = Category::factory()->create();
            $category->delete();
            expect(Category::find($category->id))->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete Categories', function () {
            $categories = Category::factory()->count(5)->create();
            foreach ($categories as $category) {
                $category->delete();
            }
            foreach ($categories as $category) {
                expect(Category::find($category->id))->toBeNull();
            }
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation & Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {
        it('cannot create a category with an empty name', function () {
            $category = new Category(['name' => '']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors()['name'] ?? null)->toBe('The name field is required.');
        })->done(assignee: 'ghostridr');

        it('cannot create a category with a duplicate name', function () {
            Category::factory()->create(['name' => 'Unique Category']);
            $category = new Category(['name' => 'Unique Category']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors()['name'] ?? null)->toBe('The name field must be unique.');
        })->done(assignee: 'ghostridr');

        it('cannot attach a non-existent category', function () {
            $announcement = Announcement::factory()->create();
            expect(fn () => $announcement->categories()->attach(999999))->toThrow(QueryException::class);

            $blog = Blog::factory()->create();
            expect(fn () => $blog->categories()->attach(999999))->toThrow(QueryException::class);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Events & Observers
    // ───────────────────────────────────────────────────────────────────────────
    describe('Events', function () {
        it('fires created event when Category is made', function () {
            $called = false;
            Category::created(function () use (&$called) {
                $called = true;
            });
            Category::factory()->create();
            expect($called)->toBeTrue();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create Categories with non-English names', function () {
            $category = Category::factory()->create(['name' => 'タグ']);
            expect($category->name)->toBe('タグ');
        })->done(assignee: 'ghostridr');

        it('can create Categories with emoji', function () {
            $category = Category::factory()->create(['name' => '🔥']);
            expect($category->name)->toBe('🔥');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Relationships
    // ───────────────────────────────────────────────────────────────────────────
    describe('Category Relationships', function () {
        // Announcement relationships
        describe('Announcements', function () {
            it('can associate and retrieve a single category for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $category = Category::factory()->create();
                $announcement->categories()->attach($category->id);
                expect($announcement->categories()->count())->toBe(1);
                expect($announcement->categories->first()->id)->toBe($category->id);
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple categories for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach a single category from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $category = Category::factory()->create();
                $announcement->categories()->attach($category->id);
                $announcement->categories()->detach($category->id);
                expect($announcement->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach all categories from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                $announcement->categories()->detach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('cannot attach a non-existent category to an announcement', function () {
                $announcement = Announcement::factory()->create();
                expect(fn () => $announcement->categories()->attach(999999))->toThrow(QueryException::class);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // Blog relationships
        describe('Blogs', function () {
            it('can associate and retrieve a single category for a blog', function () {
                $blog = Blog::factory()->create();
                $category = Category::factory()->create();
                $blog->categories()->attach($category->id);
                expect($blog->categories()->count())->toBe(1);
                expect($blog->categories->first()->id)->toBe($category->id);
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple categories for a blog', function () {
                $blog = Blog::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can detach a single category from a blog', function () {
                $blog = Blog::factory()->create();
                $category = Category::factory()->create();
                $blog->categories()->attach($category->id);
                $blog->categories()->detach($category->id);
                expect($blog->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('can detach all categories from a blog', function () {
                $blog = Blog::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                $blog->categories()->detach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(0);
            })->done(assignee: 'ghostridr');

            it('cannot attach a non-existent category to a blog', function () {
                $blog = Blog::factory()->create();
                expect(fn () => $blog->categories()->attach(999999))->toThrow(QueryException::class);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // User relationships
        describe('Users', function () {
            it('can associate and retrieve a user for a category', function () {
                $author = User::factory()->create();
                $category = Category::factory()->create(['author_id' => $author->id]);
                expect($category->author)->toBeInstanceOf(User::class);
                expect($category->author->id)->toBe($author->id);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Scope & Soft Deletes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Scope & Active', function () {
        it('active scope returns only active categories', function () {
            Category::factory()->create(['is_active' => true]);
            Category::factory()->create(['is_active' => false]);
            $active = Category::active()->get();
            expect($active->count())->toBe(1);
            expect($active->first()->is_active)->toBeTruthy();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    describe('Soft Deletes', function () {
        it('deletes categories from queries after delete (no soft deletes)', function () {
            $category = Category::factory()->create();
            $category->delete();
            $found = Category::query()->find($category->id);
            expect($found)->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Parent/Child & User Relationships
    // ───────────────────────────────────────────────────────────────────────────
    describe('Hierarchy & Users', function () {
        it('relates to an author user', function () {
            $author = User::factory()->create();
            $category = Category::factory()->create(['author_id' => $author->id]);
            expect($category->author)->toBeInstanceOf(User::class);
            expect($category->author->id)->toBe($author->id);
        })->done(assignee: 'ghostridr');

        it('supports parent and children relations', function () {
            $parent = Category::factory()->create(['name' => 'Parent']);
            $child = Category::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);
            expect($child->parent->id)->toBe($parent->id);
            expect($parent->children()->count())->toBe(1);
            expect($parent->children->first()->id)->toBe($child->id);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation Rules
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a name', function () {
            $category = new Category(['name' => '']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors()['name'] ?? null)->toBe('The name field is required.');
        })->done(assignee: 'ghostridr');

        it('requires a unique name', function () {
            Category::factory()->create(['name' => 'Unique Category']);
            $category2 = Category::factory()->make(['name' => 'Unique Category']);
            expect($category2->isValid())->toBeFalse();
            expect($category2->getErrors()['name'] ?? null)->toBe('The name field must be unique.');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
