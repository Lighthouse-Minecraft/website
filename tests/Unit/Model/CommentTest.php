<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Model', function () {

    // ───────────────────────────────────────────────────────────────────────────
    // CRUD Operations
    // ───────────────────────────────────────────────────────────────────────────
    describe('CRUD', function () {
        it('can create a comment', function () {
            $comment = Comment::factory()->create(['content' => 'Test comment']);
            expect($comment)->toBeInstanceOf(Comment::class);
            expect($comment->content)->toBe('Test comment');
        })->done(assignee: 'ghostridr');

        it('can view a comment', function () {
            $comment = Comment::factory()->create();
            $found = Comment::find($comment->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($comment->id);
        })->done(assignee: 'ghostridr');

        it('can update a comment', function () {
            $comment = Comment::factory()->create();
            $comment->update(['content' => 'Updated content']);
            expect($comment->fresh()->content)->toBe('Updated content');
        })->done(assignee: 'ghostridr');

        it('can delete a comment', function () {
            $comment = Comment::factory()->create();
            $comment->delete();
            expect(Comment::find($comment->id))->toBeNull();
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Edge Cases
    // ───────────────────────────────────────────────────────────────────────────
    describe('Edge Cases', function () {
        it('cannot create a comment with empty content', function () {
            $comment = new Comment(['content' => '']);
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content field is required.');
        })->done(assignee: 'ghostridr');

        it('cannot create a comment with duplicate content', function () {
            Comment::factory()->create(['content' => 'Unique Content']);
            $comment = new Comment(['content' => 'Unique Content']);
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content field must be unique.');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

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
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple comments for an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comments = Comment::factory()->count(2)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                expect($announcement->comments()->count())->toBe(2);
            })->done(assignee: 'ghostridr');

            it('can delete a single comment from an announcement', function () {
                $announcement = Announcement::factory()->create();
                $comments = Comment::factory()->count(2)->create([
                    'commentable_id' => $announcement->id,
                    'commentable_type' => Announcement::class,
                ]);
                $comments[0]->delete();
                expect($announcement->comments()->count())->toBe(1);
                expect($announcement->comments->first()->id)->toBe($comments[1]->id);
            })->done(assignee: 'ghostridr');

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
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

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
            })->done(assignee: 'ghostridr');

            it('can associate and retrieve multiple comments for a blog', function () {
                $blog = Blog::factory()->create();
                $comments = Comment::factory()->count(3)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                expect($blog->comments()->count())->toBe(3);
            })->done(assignee: 'ghostridr');

            it('can delete a single comment from a blog', function () {
                $blog = Blog::factory()->create();
                $comments = Comment::factory()->count(2)->create([
                    'commentable_id' => $blog->id,
                    'commentable_type' => Blog::class,
                ]);
                $comments[0]->delete();
                expect($blog->comments()->count())->toBe(1);
                expect($blog->comments->first()->id)->toBe($comments[1]->id);
            })->done(assignee: 'ghostridr');

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
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');

        // User relationships
        describe('Users', function () {
            it('can associate and retrieve a user for a comment', function () {
                $author = User::factory()->create();
                $comment = Comment::factory()->create(['author_id' => $author->id]);
                expect($comment->author)->toBeInstanceOf(User::class);
                expect($comment->author->id)->toBe($author->id);
            })->done(assignee: 'ghostridr');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // ───────────────────────────────────────────────────────────────────────────
    // Validation Rules
    // ───────────────────────────────────────────────────────────────────────────
    describe('Validation', function () {
        it('requires a content for comments', function () {
            $comment = new Comment;
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content field is required.');
        })->done(assignee: 'ghostridr');

        it('limits comment content length', function () {
            $comment = new Comment(['content' => str_repeat('A', 2001)]);
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The content may not be greater than 2000 characters.');
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
