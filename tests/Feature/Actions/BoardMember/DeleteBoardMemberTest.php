<?php

declare(strict_types=1);

use App\Actions\DeleteBoardMember;
use App\Models\BoardMember;
use App\Models\User;

uses()->group('board-members');

it('deletes an unlinked board member', function () {
    $boardMember = BoardMember::factory()->create();

    DeleteBoardMember::run($boardMember);

    $this->assertDatabaseMissing('board_members', ['id' => $boardMember->id]);
});

it('clears is_board_member flag when deleting a linked board member', function () {
    $user = User::factory()->create(['is_board_member' => true]);
    $boardMember = BoardMember::factory()->linkedTo($user->id)->create();

    DeleteBoardMember::run($boardMember);

    expect($user->fresh()->is_board_member)->toBeFalse();
    $this->assertDatabaseMissing('board_members', ['id' => $boardMember->id]);
});

it('records activity before deletion', function () {
    $boardMember = BoardMember::factory()->create(['display_name' => 'Test Delete']);

    DeleteBoardMember::run($boardMember);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'board_member_deleted',
    ]);
});
