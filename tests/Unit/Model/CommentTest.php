<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Feature', function () {
    // ───────────────────────────────────────────────────────────────────────────
    // API
    // ───────────────────────────────────────────────────────────────────────────
    describe('API', function () {
        it('can list comments via API', function () {
            Comment::factory()->count(3)->create();
            $response = $this->getJson('/api/comments');
            expect($response->json())->toHaveCount(3);
        })->todo('Implement API endpoint to list all comments and ensure it returns the correct count.');
    })->todo('Implement API resource tests for Comment model.');

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD Operations
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a comment', function () {
            $comment = Comment::factory()->create(['content' => 'Test comment']);
            expect($comment)->toBeInstanceOf(Comment::class);
            expect($comment->content)->toBe('Test comment');
        })->todo('Test comment creation and verify the comment is persisted with correct attributes.');

        it('can view a comment', function () {
            $comment = Comment::factory()->create();
            $found = Comment::find($comment->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($comment->id);
        })->todo('Test retrieving a comment by ID and verify its attributes.');

        it('can update a comment', function () {
            $comment = Comment::factory()->create();
            $comment->update(['content' => 'Updated content']);
            expect($comment->fresh()->content)->toBe('Updated content');
        })->todo('Test updating a comment’s content and verify the change is persisted.');

        it('can delete a comment', function () {
            $comment = Comment::factory()->create();
            $comment->delete();
            expect(Comment::find($comment->id))->toBeNull();
        })->todo('Test deleting a comment and verify it is removed from the database.');
    })->todo('Test basic CRUD operations for Comment model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {
        it('cannot create a comment with empty content', function () {
            $comment = new Comment(['content' => '']);
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content field is required.');
        })->todo('Implement validation logic in Comment model to require a non-empty content and return errors if missing.');

        it('cannot create a comment with duplicate content', function () {
            Comment::factory()->create(['content' => 'Unique Content']);
            $comment = new Comment(['content' => 'Unique Content']);
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content has already been taken.');
        })->todo('Implement validation logic in Comment model to require unique content and return errors if duplicate.');

        it('cannot attach a non-existent comment', function () {
            $announcement = Announcement::factory()->create();
            $announcement->comments()->attach(999999); // unlikely to exist
            expect($announcement->comments()->count())->toBe(0);

            $blog = Blog::factory()->create();
            $blog->comments()->attach(999999); // unlikely to exist
            expect($blog->comments()->count())->toBe(0);
        })->todo('Test that attaching a non-existent comment to an announcement or blog does not create a relationship.');
    });

    // ───────────────────────────────────────────────────────────────────────────
    // Relationships
    // ───────────────────────────────────────────────────────────────────────────
    describe('Comment Relationships', function () {
        // Announcement Relationships
        describe('Announcements', function () {
            it('can associate and retrieve a single comment for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                expect($announcement->comments()->count())->toBe(1);
                expect($announcement->comments->first()->id)->toBe($comment->id);
            })->todo('Ensure Announcement model has a polymorphic comments relationship and can retrieve a single associated comment.');

            it('can associate and retrieve multiple comments for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comments = Comment::factory()->count(2)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                expect($announcement->comments()->count())->toBe(2);
            })->todo('Ensure Announcement model can retrieve multiple associated comments via polymorphic relationship.');

            it('can delete a single comment from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comments = Comment::factory()->count(2)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                $comments[0]->delete();
                expect($announcement->comments()->count())->toBe(1);
                expect($announcement->comments->first()->id)->toBe($comments[1]->id);
            })->todo('Test that deleting a comment removes it from the announcement’s comments collection.');

            it('can delete all comments from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                foreach ($comments as $comment) {
                    $comment->delete();
                }
                expect($announcement->comments()->count())->toBe(0);
            })->todo('Test that deleting all comments removes them from the announcement’s comments collection.');
        })->todo('Test polymorphic relationships and comment management for Announcement model.');

        // Blog Relationships
        describe('Blogs', function () {
            it('can associate and retrieve a single comment for a blog', function () {
                $blog = Blog::factory()->create();
                $comment = Comment::factory()->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                expect($blog->comments()->count())->toBe(1);
                expect($blog->comments->first()->id)->toBe($comment->id);
            })->todo('Ensure Blog model has a polymorphic comments relationship and can retrieve a single associated comment.');

            it('can associate and retrieve multiple comments for a blog', function () {
                $blog = Blog::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                expect($blog->comments()->count())->toBe(3);
            })->todo('Ensure Blog model can retrieve multiple associated comments via polymorphic relationship.');

            it('can delete a single comment from a blog', function () {
                $blog = Blog::factory()->create();
                $comments = Comment::factory()->count(2)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                $comments[0]->delete();
                expect($blog->comments()->count())->toBe(1);
                expect($blog->comments->first()->id)->toBe($comments[1]->id);
            })->todo('Test that deleting a comment removes it from the blog’s comments collection.');

            it('can delete all comments from a blog', function () {
                $blog = Blog::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                foreach ($comments as $comment) {
                    $comment->delete();
                }
                expect($blog->comments()->count())->toBe(0);
            })->todo('Test that deleting all comments removes them from the blog’s comments collection.');

            // User relationships
            describe('Users', function () {
                it('can associate and retrieve a user for a comment', function () {
                    $author = User::factory()->create();
                    $comment = Comment::factory()->create(['author_id' => $author->id]);
                    expect($comment->author)->toBeInstanceOf(User::class);
                    expect($comment->author->id)->toBe($author->id);
                })->todo('Ensure Comment model has an author relationship and can retrieve the associated user.');
            });
        })->todo('Test polymorphic relationships and comment management for Blog model.');

        // User Relationships
        describe('Users', function () {
            it('can associate and retrieve a user for a comment', function () {
                $author = User::factory()->create();
                $comment = Comment::factory()->create(['author_id' => $author->id]);
                expect($comment->author)->toBeInstanceOf(User::class);
                expect($comment->author->id)->toBe($author->id);
            })->todo('Ensure Comment model has an author relationship and can retrieve the associated user.');
        })->todo('Test author relationship and user association for Comment model.');
    })->todo('Test polymorphic relationships and comment management for Comment model.');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation Rules
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a content for comments', function () {
            $comment = new Comment;
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content field is required.');
        })->todo('Implement validation logic in Comment model to require a non-empty content and return errors if missing.');

        it('limits comment content length', function () {
            $comment = new Comment(['content' => str_repeat('A', 256)]);
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content may not be greater than 255 characters.');
        })->todo('Implement validation logic in Comment model to limit content length and return errors if exceeded.');
    })->todo('Test validation rules for Comment model.');
})->todo('Implement all Comment feature tests.');
