<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
// API/Resource describe
describe('API', function () {
    it('can list categories via API', function () {
        Category::factory()->count(3)->create();
        $response = $this->getJson('/api/categories');
        expect($response->json())->toHaveCount(3);
    })->todo('');

    // Announcements relationships
    describe('Announcements', function () {
        it('can associate and retrieve a single category for an announcement', function () {
            $announcement = Announcement::factory()->create();
            $category = Category::factory()->create();
            $announcement->categories()->attach($category->id);
            expect($announcement->categories()->count())->toBe(1);
            expect($announcement->categories->first()->id)->toBe($category->id);
        })->todo('');

        it('can associate and retrieve multiple categories for an announcement', function () {
            $announcement = Announcement::factory()->create();
            $categories = Category::factory()->count(3)->create();
            $announcement->categories()->attach($categories->pluck('id')->toArray());
            expect($announcement->categories()->count())->toBe(3);
        })->todo('');

        it('can detach a single category from an announcement', function () {
            $announcement = Announcement::factory()->create();
            $category = Category::factory()->create();
            $announcement->categories()->attach($category->id);
            $announcement->categories()->detach($category->id);
            expect($announcement->categories()->count())->toBe(0);
        })->todo('');

        it('can detach all categories from an announcement', function () {
            $announcement = Announcement::factory()->create();
            $categories = Category::factory()->count(3)->create();
            $announcement->categories()->attach($categories->pluck('id')->toArray());
            $announcement->categories()->detach($categories->pluck('id')->toArray());
            expect($announcement->categories()->count())->toBe(0);
        })->todo('');

        it('cannot attach a non-existent category to an announcement', function () {
            $announcement = Announcement::factory()->create();
            $announcement->categories()->attach(999999); // unlikely to exist
            expect($announcement->categories()->count())->toBe(0);
        })->todo('');
    })->todo('');

    // Authorization for Category actions
    describe('Authorization', function () {
        it('allows admin to create a category', function () {
            $this->actingAsAdmin();
            $response = $this->post('/categories', ['name' => 'New Category']);
            expect($response->status())->toBe(201);
        })->todo('');

        it('prevents non-admin from creating a category', function () {
            $this->actingAsUser();
            $response = $this->post('/categories', ['name' => 'New Category']);
            expect($response->status())->toBe(403);
        })->todo('');
    })->todo('');

    // Basic CRUD operations
    describe('CRUD', function () {
        it('can create a category', function () {
            $category = Category::factory()->create(['name' => 'Test Category']);
            expect($category)->toBeInstanceOf(Category::class);
            expect($category->name)->toBe('Test Category');
        })->todo('');

        it('can view a category', function () {
            $category = Category::factory()->create();
            $found = Category::find($category->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($category->id);
        })->todo('');

        it('can update a category', function () {
            $category = Category::factory()->create();
            $category->update(['name' => 'Updated Category']);
            expect($category->fresh()->name)->toBe('Updated Category');
        })->todo('');

        it('can delete a category', function () {
            $category = Category::factory()->create();
            $category->delete();
            expect(Category::find($category->id))->toBeNull();
        })->todo('');
    })->todo('');

    // Blog relationships
    describe('Blogs', function () {
        it('can associate and retrieve a single category for a blog', function () {
            $blog = Blog::factory()->create();
            $category = Category::factory()->create();
            $blog->categories()->attach($category->id);
            expect($blog->categories()->count())->toBe(1);
            expect($blog->categories->first()->id)->toBe($category->id);
        })->todo('');

        it('can associate and retrieve multiple categories for a blog', function () {
            $blog = Blog::factory()->create();
            $categories = Category::factory()->count(3)->create();
            $blog->categories()->attach($categories->pluck('id')->toArray());
            expect($blog->categories()->count())->toBe(3);
        })->todo('');

        it('can detach a single category from a blog', function () {
            $blog = Blog::factory()->create();
            $category = Category::factory()->create();
            $blog->categories()->attach($category->id);
            $blog->categories()->detach($category->id);
            expect($blog->categories()->count())->toBe(0);
        })->todo('');

        it('can detach all categories from a blog', function () {
            $blog = Blog::factory()->create();
            $categories = Category::factory()->count(3)->create();
            $blog->categories()->attach($categories->pluck('id')->toArray());
            $blog->categories()->detach($categories->pluck('id')->toArray());
            expect($blog->categories()->count())->toBe(0);
        })->todo('');
    })->todo('');

    // Cleanup and bulk actions
    describe('Cleanup', function () {
        it('can delete all Categories', function () {
            Category::factory()->count(5)->create();
            expect(Category::count())->toBe(5);
            Category::truncate();
            expect(Category::count())->toBe(0);
        })->todo('');
    })->todo('');

    // Edge cases
    describe('Edge Cases', function () {
        it('cannot create a category with an empty name', function () {
            $category = new Category(['name' => '']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors())->toContain('The name field is required.');
        })->todo('');

        it('cannot create a category with a duplicate name', function () {
            Category::factory()->create(['name' => 'Unique Category']);
            $category = new Category(['name' => 'Unique Category']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors())->toContain('The name has already been taken.');
        })->todo('');

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
        it('fires created event when Category is made', function () {
            $called = false;
            Category::created(function () use (&$called) {
                $called = true;
            });
            Category::factory()->create();
            expect($called)->toBeTrue();
        })->todo('');
    })->todo('');

    // Localization/Internationalization
    describe('Localization', function () {
        it('can create Categories with non-English names', function () {
            $Category = Category::factory()->create(['name' => 'タグ']);
            expect($Category->name)->toBe('タグ');
        })->todo('');
        it('can create Categories with emoji', function () {
            $Category = Category::factory()->create(['name' => '🔥']);
            expect($Category->name)->toBe('🔥');
        })->todo('');
    })->todo('');

    // Performance
    describe('Performance', function () {
        it('can bulk attach many Categories to a blog efficiently', function () {
            $blog = Blog::factory()->create();
            $Categories = Category::factory()->count(50)->create();
            $blog->Categories()->attach($Categories->pluck('id')->toArray());
            expect($blog->Categories()->count())->toBe(50);
        })->todo('');
    })->todo('');

    // Security
    describe('Security', function () {
        it('prevents unauthorized user from deleting a Category', function () {
            $Category = Category::factory()->create();
            $this->actingAsUser();
            $response = $this->delete('/Categories/'.$Category->id);
            expect($response->status())->toBe(403);
        })->todo('');
    })->todo('');

    // Soft deletes
    describe('Soft Deletes', function () {
        it('does not return soft deleted Categories in queries', function () {
            $Category = Category::factory()->create();
            $Category->delete();
            $found = Category::query()->find($Category->id);
            expect($found)->toBeNull();
        })->todo('');
    })->todo('');

    // User relationships
    describe('Users', function () {
        it('can associate and retrieve a user for a category', function () {
            $author = User::factory()->create();
            $category = Category::factory()->create(['author_id' => $author->id]);
            expect($category->author)->toBeInstanceOf(User::class);
            expect($category->author->id)->toBe($author->id);
        })->todo('');
    })->todo('');

    // Validation rules for Categories
    describe('Validation', function () {
        it('requires a name', function () {
            $category = new Category(['name' => '']);
            expect($category->isValid())->toBeFalse();
            expect($category->getErrors())->toContain('The name field is required.');
        })->todo('');

        it('requires a unique name', function () {
            Category::factory()->create(['name' => 'Unique Category']);
            $category2 = Category::factory()->make(['name' => 'Unique Category']);
            expect($category2->isValid())->toBeFalse();
            expect($category2->getErrors())->toContain('The name field must be unique.');
        })->todo('');
    })->todo('');
})->todo('');
