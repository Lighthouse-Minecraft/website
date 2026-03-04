<?php

declare(strict_types=1);

use App\Actions\UpdateBoardMember;
use App\Models\BoardMember;

uses()->group('board-members');

it('updates display name and title', function () {
    $boardMember = BoardMember::factory()->create([
        'display_name' => 'Old Name',
        'title' => 'Old Title',
    ]);

    UpdateBoardMember::run(
        boardMember: $boardMember,
        displayName: 'New Name',
        title: 'New Title',
    );

    $boardMember->refresh();
    expect($boardMember->display_name)->toBe('New Name');
    expect($boardMember->title)->toBe('New Title');
});

it('records activity when updating', function () {
    $boardMember = BoardMember::factory()->create();

    UpdateBoardMember::run(
        boardMember: $boardMember,
        displayName: 'Updated',
    );

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'board_member_updated',
        'subject_id' => $boardMember->id,
        'subject_type' => BoardMember::class,
    ]);
});
