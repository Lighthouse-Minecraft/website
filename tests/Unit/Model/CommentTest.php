<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Comment Feature', function () {
    // API/Resource describe
    describe('API', function () {
        it('can list comments via API', function () {
            Comment::factory()->count(3)->create();
            $response = $this->getJson('/api/comments');
            expect($response->json())->toHaveCount(3);
        })->todo('');
    })->todo('');

    // Announcement relationships
    describe('Announcements', function () {
        it('can associate and retrieve a single comment for an announcement', function () {
            $announcement = Announcement::factory()->create();
            $comment = Comment::factory()->create([
                'commentable_id' => $announcement->id,
                'commentable_type' => Announcement::class,
            ]);
            expect($announcement->comments()->count())->toBe(1);
            expect($announcement->comments->first()->id)->toBe($comment->id);
        })->todo('');

        it('can associate and retrieve multiple comments for an announcement', function () {
            $announcement = Announcement::factory()->create();
            $comments = Comment::factory()->count(2)->create([
                'commentable_id' => $announcement->id,
                'commentable_type' => Announcement::class,
            ]);
            expect($announcement->comments()->count())->toBe(2);
        })->todo('');

        it('can delete a single comment from an announcement', function () {
            $announcement = Announcement::factory()->create();
            $comments = Comment::factory()->count(2)->create([
                'commentable_id' => $announcement->id,
                'commentable_type' => Announcement::class,
            ]);
            $comments[0]->delete();
            expect($announcement->comments()->count())->toBe(1);
            expect($announcement->comments->first()->id)->toBe($comments[1]->id);
        })->todo('');

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
        })->todo('');
    })->todo('');

    // Basic CRUD operations
    describe('CRUD', function () {
        it('can create a comment', function () {
            $comment = Comment::factory()->create(['body' => 'Test comment']);
            expect($comment)->toBeInstanceOf(Comment::class);
            expect($comment->body)->toBe('Test comment');
        })->todo('');

        it('can view a comment', function () {
            $comment = Comment::factory()->create();
            $found = Comment::find($comment->id);
            expect($found)->not->toBeNull();
            expect($found->id)->toBe($comment->id);
        })->todo('');

        it('can update a comment', function () {
            $comment = Comment::factory()->create();
            $comment->update(['body' => 'Updated body']);
            expect($comment->fresh()->body)->toBe('Updated body');
        })->todo('');

        it('can delete a comment', function () {
            $comment = Comment::factory()->create();
            $comment->delete();
            expect(Comment::find($comment->id))->toBeNull();
        })->todo('');
    })->todo('');

    // Blog relationships
    describe('Blogs', function () {
        it('can associate and retrieve a single comment for a blog', function () {
            $blog = Blog::factory()->create();
            $comment = Comment::factory()->create([
                'commentable_id' => $blog->id,
                'commentable_type' => Blog::class,
            ]);
            expect($blog->comments()->count())->toBe(1);
            expect($blog->comments->first()->id)->toBe($comment->id);
        })->todo('');

        it('can associate and retrieve multiple comments for a blog', function () {
            $blog = Blog::factory()->create();
            $comments = Comment::factory()->count(3)->create([
                'commentable_id' => $blog->id,
                'commentable_type' => Blog::class,
            ]);
            expect($blog->comments()->count())->toBe(3);
        })->todo('');

        it('can delete a single comment from a blog', function () {
            $blog = Blog::factory()->create();
            $comments = Comment::factory()->count(2)->create([
                'commentable_id' => $blog->id,
                'commentable_type' => Blog::class,
            ]);
            $comments[0]->delete();
            expect($blog->comments()->count())->toBe(1);
            expect($blog->comments->first()->id)->toBe($comments[1]->id);
        })->todo('');

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
        })->todo('');
    })->todo('');

    // User relationships
    describe('Users', function () {
        it('can associate and retrieve a user for a comment', function () {
            $author = User::factory()->create();
            $comment = Comment::factory()->create(['author_id' => $author->id]);
            expect($comment->author)->toBeInstanceOf(User::class);
            expect($comment->author->id)->toBe($author->id);
        })->todo('');
    })->todo('');

    // Validation rules for comments
    describe('Validation', function () {
        it('requires a body', function () {
            $comment = new Comment(['body' => '']);
            expect($comment->isValid())->toBeFalse();
            expect($comment->getErrors())->toContain('The body field is required.');
        })->todo('');
    })->todo('');
})->todo('');
