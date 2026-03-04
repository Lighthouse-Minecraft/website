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
    $boardMemberId = $boardMember->id;

    DeleteBoardMember::run($boardMember);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'board_member_deleted',
        'subject_id' => $boardMemberId,
        'subject_type' => BoardMember::class,
    ]);
});

it('deletes photo from storage when deleting board member', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    \Illuminate\Support\Facades\Storage::disk('public')->put('board-member-photos/test.jpg', 'dummy');

    $boardMember = BoardMember::factory()->create(['photo_path' => 'board-member-photos/test.jpg']);

    DeleteBoardMember::run($boardMember);

    \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('board-member-photos/test.jpg');
    $this->assertDatabaseMissing('board_members', ['id' => $boardMember->id]);
});
