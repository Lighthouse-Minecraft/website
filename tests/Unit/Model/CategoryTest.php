<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Category feature test
describe('Category Model', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // API
    // ───────────────────────────────────────────────────────────────────────────
    describe('API', function () {
        it('can list categories via API', function () {
            Category::factory()->count(3)->create();
            $response = $this->getJson('/api/categories');
            expect($response->json())->toHaveCount(3);
        })->todo('Implement API endpoint to list all categories and ensure it returns the correct count.');
    })->todo('Implement API resource tests for Category model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Authorization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Authorization', function () {
        it('allows admin to create a category', function () {
            $this->actingAsAdmin();
            $response = $this->post('/categories', ['name' => 'New Category']);
            expect($response->status())->toBe(201);
        })->todo('Test that an admin user can create a category via the API.');

        it('prevents non-admin from creating a category', function () {
            $this->actingAsUser();
            $response = $this->post('/categories', ['name' => 'New Category']);
            expect($response->status())->toBe(403);
        })->todo('Test that a non-admin user cannot create a category via the API.');
    })->todo('Test authorization rules for category creation.');

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD Operations
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a category', function () {
            $category = Category::factory()->create(['name' => 'Test Category']);
            expect($category)->toBeInstanceOf(Category::class);
            expect($category->name)->toBe('Test Category');
        })->todo('Test category creation and verify the category is persisted with correct attributes.');

        it('can view a category', function () {
            $category = Category::factory()->create();
            $found = Category::find($category->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($category->id);
        })->todo('Test retrieving a category by ID and verify its attributes.');

        it('can update a category', function () {
            $category = Category::factory()->create();
            $category->update(['name' => 'Updated Category']);
            expect($category->fresh()->name)->toBe('Updated Category');
        })->todo('Test updating a category’s name and verify the change is persisted.');

        it('can delete a category', function () {
            $category = Category::factory()->create();
            $category->delete();
            expect(Category::find($category->id))->toBeNull();
        })->todo('Test deleting a category and verify it is removed from the database.');
    })->todo('Test basic CRUD operations for Category model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ───────────────────────────────────────────────────────────────────────────
    describe('Cleanup', function () {
        it('can delete all Categories', function () {
            Category::factory()->count(5)->create();
            expect(Category::count())->toBe(5);
            Category::truncate();
            expect(Category::count())->toBe(0);
        })->todo('Test bulk deletion of categories and verify all are removed from the database.');
    })->todo('Test bulk deletion and cleanup actions for Category model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {
        it('cannot create a category with an empty name', function () {
            $category = new Category(['name' => '']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors())->toContain('The name field is required.');
        })->todo('Implement validation logic in Category model to require a non-empty name and return errors if missing.');

        it('cannot create a category with a duplicate name', function () {
            Category::factory()->create(['name' => 'Unique Category']);
            $category = new Category(['name' => 'Unique Category']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors())->toContain('The name has already been taken.');
        })->todo('Implement validation logic in Category model to require a unique name and return errors if duplicate.');

        it('cannot attach a non-existent category', function () {
            $announcement = Announcement::factory()->create();
            $announcement->categories()->attach(999999); // unlikely to exist
            expect($announcement->categories()->count())->toBe(0);

            $blog = Blog::factory()->create();
            $blog->categories()->attach(999999); // unlikely to exist
            expect($blog->categories()->count())->toBe(0);
        })->todo('Test that attaching a non-existent category to an announcement or blog does not create a relationship.');
    })->todo('Test edge cases for category relationships and validation.');

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
        })->todo('Test that the created event is fired when a category is created.');
    })->todo('Test event and observer logic for Category model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Localization
    // ───────────────────────────────────────────────────────────────────────────
    describe('Localization', function () {
        it('can create Categories with non-English names', function () {
            $Category = Category::factory()->create(['name' => 'タグ']);
            expect($Category->name)->toBe('タグ');
        })->todo('Test category creation with non-English names and verify correct storage.');

        it('can create Categories with emoji', function () {
            $Category = Category::factory()->create(['name' => '🔥']);
            expect($Category->name)->toBe('🔥');
        })->todo('Test category creation with emoji and verify correct storage.');
    })->todo('Test localization and internationalization for Category names.');

    // ───────────────────────────────────────────────────────────────────────────
    // Performance
    // ───────────────────────────────────────────────────────────────────────────
    describe('Performance', function () {
        it('can bulk attach many Categories to a blog efficiently', function () {
            $blog = Blog::factory()->create();
            $Categories = Category::factory()->count(50)->create();
            $blog->Categories()->attach($Categories->pluck('id')->toArray());
            expect($blog->Categories()->count())->toBe(50);
        })->todo('Test bulk attaching many categories to a blog and verify performance and correctness.');
    })->todo('Test performance for bulk category operations.');

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
            })->todo('Ensure Announcement model can associate and retrieve a single category via many-to-many relationship.');

            it('can associate and retrieve multiple categories for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(3);
            })->todo('Ensure Announcement model can associate and retrieve multiple categories via many-to-many relationship.');

            it('can detach a single category from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $category = Category::factory()->create();
                $announcement->categories()->attach($category->id);
                $announcement->categories()->detach($category->id);
                expect($announcement->categories()->count())->toBe(0);
            })->todo('Test detaching a single category from an announcement and verify the relationship is removed.');

            it('can detach all categories from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $announcement->categories()->attach($categories->pluck('id')->toArray());
                $announcement->categories()->detach($categories->pluck('id')->toArray());
                expect($announcement->categories()->count())->toBe(0);
            })->todo('Test detaching all categories from an announcement and verify no categories remain associated.');

            it('cannot attach a non-existent category to an announcement', function () {
                $announcement = Announcement::factory()->create();
                $announcement->categories()->attach(999999); // unlikely to exist
                expect($announcement->categories()->count())->toBe(0);
            })->todo('Test that attaching a non-existent category to an announcement does not create a relationship.');
        })->todo('Test many-to-many relationships and category management for Announcement model.');

        // Blog relationships
        describe('Blogs', function () {
            it('can associate and retrieve a single category for a blog', function () {
                $blog = Blog::factory()->create();
                $category = Category::factory()->create();
                $blog->categories()->attach($category->id);
                expect($blog->categories()->count())->toBe(1);
                expect($blog->categories->first()->id)->toBe($category->id);
            })->todo('Ensure Blog model can associate and retrieve a single category via many-to-many relationship.');

            it('can associate and retrieve multiple categories for a blog', function () {
                $blog = Blog::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(3);
            })->todo('Ensure Blog model can associate and retrieve multiple categories via many-to-many relationship.');

            it('can detach a single category from a blog', function () {
                $blog = Blog::factory()->create();
                $category = Category::factory()->create();
                $blog->categories()->attach($category->id);
                $blog->categories()->detach($category->id);
                expect($blog->categories()->count())->toBe(0);
            })->todo('Test detaching a single category from a blog and verify the relationship is removed.');

            it('can detach all categories from a blog', function () {
                $blog = Blog::factory()->create();
                $categories = Category::factory()->count(3)->create();
                $blog->categories()->attach($categories->pluck('id')->toArray());
                $blog->categories()->detach($categories->pluck('id')->toArray());
                expect($blog->categories()->count())->toBe(0);
            })->todo('Test detaching all categories from a blog and verify no categories remain associated.');
        })->todo('Test many-to-many relationships and category management for Blog model.');

        // User relationships
        describe('Users', function () {
            it('can associate and retrieve a user for a category', function () {
                $author = User::factory()->create();
                $category = Category::factory()->create(['author_id' => $author->id]);
                expect($category->author)->toBeInstanceOf(User::class);
                expect($category->author->id)->toBe($author->id);
            })->todo('Ensure Category model has an author relationship and can retrieve the associated user.');
        });
    })->todo('Test many-to-many relationships and category management for Category model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Security
    // ───────────────────────────────────────────────────────────────────────────
    describe('Security', function () {
        it('prevents unauthorized user from deleting a Category', function () {
            $Category = Category::factory()->create();
            $this->actingAsUser();
            $response = $this->delete('/Categories/'.$Category->id);
            expect($response->status())->toBe(403);
        })->todo('Test that a non-admin user cannot delete a category via the API.');
    })->todo('Test security and authorization for category deletion.');

    // ───────────────────────────────────────────────────────────────────────────
    // Soft Deletes
    // ───────────────────────────────────────────────────────────────────────────
    describe('Soft Deletes', function () {
        it('does not return soft deleted Categories in queries', function () {
            $Category = Category::factory()->create();
            $Category->delete();
            $found = Category::query()->find($Category->id);
            expect($found)->toBeNull();
        })->todo('Test that soft deleted categories are not returned in queries.');
    })->todo('Test soft delete behavior for Category model.');

    // ───────────────────────────────────────────────────────────────────────────
    // User Relationships
    // ───────────────────────────────────────────────────────────────────────────
    describe('Users', function () {
        it('can associate and retrieve a user for a category', function () {
            $author = User::factory()->create();
            $category = Category::factory()->create(['author_id' => $author->id]);
            expect($category->author)->toBeInstanceOf(User::class);
            expect($category->author->id)->toBe($author->id);
        })->todo('Ensure Category model has an author relationship and can retrieve the associated user.');
    })->todo('Test author relationship and user association for Category model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation Rules
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a name', function () {
            $category = new Category(['name' => '']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors())->toContain('The name field is required.');
        })->todo('Implement validation logic in Category model to require a non-empty name and return errors if missing.');

        it('requires a unique name', function () {
            Category::factory()->create(['name' => 'Unique Category']);
            $category2 = Category::factory()->make(['name' => 'Unique Category']);
            expect($category2->isValid())->toBeFalse();
            expect($category2->getErrors())->toContain('The name field must be unique.');
        })->todo('Implement validation logic in Category model to require a unique name and return errors if duplicate.');
    })->todo('Test validation rules for Category model.');
})->todo('Implement all Category feature tests.');
