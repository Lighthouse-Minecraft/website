<?php

declare(strict_types=1);

use App\Actions\LinkBoardMemberToUser;
use App\Models\BoardMember;
use App\Models\User;

uses()->group('board-members');

it('links a user to a board member', function () {
    $user = User::factory()->create();
    $boardMember = BoardMember::factory()->create();

    LinkBoardMemberToUser::run($boardMember, $user);

    expect($boardMember->fresh()->user_id)->toBe($user->id);
});

it('sets is_board_member flag on user when linking', function () {
    $user = User::factory()->create(['is_board_member' => false]);
    $boardMember = BoardMember::factory()->create();

    LinkBoardMemberToUser::run($boardMember, $user);

    expect($user->fresh()->is_board_member)->toBeTrue();
});

it('clears existing board membership when user is already linked to another', function () {
    $user = User::factory()->create(['is_board_member' => true]);
    $oldMembership = BoardMember::factory()->linkedTo($user->id)->create();
    $newMembership = BoardMember::factory()->create();

    LinkBoardMemberToUser::run($newMembership, $user);

    expect($oldMembership->fresh()->user_id)->toBeNull();
    expect($newMembership->fresh()->user_id)->toBe($user->id);
});

it('records activity when linking', function () {
    $user = User::factory()->create();
    $boardMember = BoardMember::factory()->create();

    LinkBoardMemberToUser::run($boardMember, $user);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'board_member_linked',
    ]);
});
