<?php

declare(strict_types=1);

use App\Actions\UnlinkBoardMemberFromUser;
use App\Models\BoardMember;
use App\Models\User;

uses()->group('board-members');

it('unlinks a user from a board member', function () {
    $user = User::factory()->create(['is_board_member' => true]);
    $boardMember = BoardMember::factory()->linkedTo($user->id)->create();

    UnlinkBoardMemberFromUser::run($boardMember);

    expect($boardMember->fresh()->user_id)->toBeNull();
});

it('clears is_board_member flag on user when unlinking', function () {
    $user = User::factory()->create(['is_board_member' => true]);
    $boardMember = BoardMember::factory()->linkedTo($user->id)->create();

    UnlinkBoardMemberFromUser::run($boardMember);

    expect($user->fresh()->is_board_member)->toBeFalse();
});

it('does nothing when board member has no linked user', function () {
    $boardMember = BoardMember::factory()->create(['user_id' => null]);

    UnlinkBoardMemberFromUser::run($boardMember);

    expect($boardMember->fresh()->user_id)->toBeNull();
});

it('records activity when unlinking', function () {
    $user = User::factory()->create(['is_board_member' => true]);
    $boardMember = BoardMember::factory()->linkedTo($user->id)->create();

    UnlinkBoardMemberFromUser::run($boardMember);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'board_member_unlinked',
    ]);
});
