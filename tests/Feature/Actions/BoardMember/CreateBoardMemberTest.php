<?php

declare(strict_types=1);

use App\Actions\CreateBoardMember;
use App\Models\BoardMember;
use App\Models\User;

uses()->group('board-members');

it('creates an unlinked board member', function () {
    $boardMember = CreateBoardMember::run(
        displayName: 'John Doe',
        title: 'Board Chair',
    );

    expect($boardMember)->toBeInstanceOf(BoardMember::class);
    $this->assertDatabaseHas('board_members', [
        'display_name' => 'John Doe',
        'title' => 'Board Chair',
        'user_id' => null,
    ]);
});

it('creates a board member linked to a user', function () {
    $user = User::factory()->create();

    $boardMember = CreateBoardMember::run(
        displayName: 'Jane Smith',
        title: 'Treasurer',
        userId: $user->id,
    );

    expect($boardMember->user_id)->toBe($user->id);
    expect($user->fresh()->is_board_member)->toBeTrue();
});

it('records activity when creating a board member', function () {
    CreateBoardMember::run(displayName: 'Test Member');

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'board_member_created',
    ]);
});

it('stores title when provided', function () {
    $boardMember = CreateBoardMember::run(
        displayName: 'Alice',
        title: 'Secretary',
    );

    expect($boardMember->title)->toBe('Secretary');
});

it('creates board member without title', function () {
    $boardMember = CreateBoardMember::run(displayName: 'Bob');

    expect($boardMember->title)->toBeNull();
});
