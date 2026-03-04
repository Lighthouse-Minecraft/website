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
        // If $user is already linked to a different board member, unlink them
        $existingMembership = BoardMember::where('user_id', $user->id)->first();
        if ($existingMembership && $existingMembership->id !== $boardMember->id) {
            $existingMembership->update(['user_id' => null]);
        }

        // If $boardMember was previously linked to a different user, clear their flag
        if ($boardMember->user_id && $boardMember->user_id !== $user->id) {
            $previousUser = User::find($boardMember->user_id);
            if ($previousUser) {
                $previousUser->update(['is_board_member' => false]);
            }
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
