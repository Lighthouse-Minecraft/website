<?php

namespace App\Actions;

use App\Models\BoardMember;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkBoardMemberToUser
{
    use AsAction;

    public static function handle(BoardMember $boardMember, User $user): void
    {
        $existingMembership = BoardMember::where('user_id', $user->id)->first();
        if ($existingMembership && $existingMembership->id !== $boardMember->id) {
            $existingMembership->update(['user_id' => null]);
        }

        $boardMember->update(['user_id' => $user->id]);
        $user->update(['is_board_member' => true]);

        RecordActivity::run(
            $boardMember,
            'board_member_linked',
            "Board member '{$boardMember->display_name}' linked to user {$user->name}"
        );
    }
}
