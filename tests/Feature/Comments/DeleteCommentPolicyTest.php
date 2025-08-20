<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to delete another users comment', function () {
    $author = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $comment = Comment::factory()
        ->withAuthor($author)
        ->forBlog()
        ->create();

    $this->actingAs($admin)
        ->delete(route('comments.destroy', $comment->id))
        ->assertSuccessful();

    expect(Comment::find($comment->id))->toBeNull();
});

it('allows officer to delete another users comment', function () {
    $author = User::factory()->create();
    $officer = User::factory()->create();
    // Give officer rank/department
    $officer->staff_rank = StaffRank::Officer;
    $officer->staff_department = StaffDepartment::Command;
    $officer->save();

    $comment = Comment::factory()
        ->withAuthor($author)
        ->forAnnouncement()
        ->create();

    $this->actingAs($officer)
        ->delete(route('comments.destroy', $comment->id))
        ->assertSuccessful();

    expect(Comment::find($comment->id))->toBeNull();
});

it('forbids regular users from deleting others comments', function () {
    $author = User::factory()->create();
    $otherUser = User::factory()->create();

    $comment = Comment::factory()
        ->withAuthor($author)
        ->forBlog()
        ->create();

    $this->actingAs($otherUser)
        ->delete(route('comments.destroy', $comment->id))
        ->assertForbidden();

    expect(Comment::find($comment->id))->not()->toBeNull();
});
