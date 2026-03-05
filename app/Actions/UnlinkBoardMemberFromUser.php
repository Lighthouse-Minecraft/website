<?php

namespace App\Actions;

use App\Models\BoardMember;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UnlinkBoardMemberFromUser
{
    use AsAction;

    public static function handle(BoardMember $boardMember): void
    {
        $user = $boardMember->user;

        if (! $user) {
            return;
        }

        $userName = $user->name;

        DB::transaction(function () use ($boardMember, $user, $userName) {
            $boardMember->update(['user_id' => null]);
            $user->update(['is_board_member' => false]);

            RecordActivity::run(
                $boardMember,
                'board_member_unlinked',
                "Board member '{$boardMember->display_name}' unlinked from user {$userName}"
            );
        });
    }
}
