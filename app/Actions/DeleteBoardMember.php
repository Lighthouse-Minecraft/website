<?php

namespace App\Actions;

use App\Models\BoardMember;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteBoardMember
{
    use AsAction;

    public static function handle(BoardMember $boardMember): void
    {
        $displayName = $boardMember->display_name;

        RecordActivity::run($boardMember, 'board_member_deleted', "Board member deleted: {$displayName}");

        if ($boardMember->user_id) {
            $user = $boardMember->user;
            if ($user) {
                $user->update(['is_board_member' => false]);
            }
        }

        if ($boardMember->photo_path) {
            Storage::disk('public')->delete($boardMember->photo_path);
        }

        $boardMember->delete();
    }
}
