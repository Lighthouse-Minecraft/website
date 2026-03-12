<?php

namespace App\Actions;

use App\Models\BoardMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteBoardMember
{
    use AsAction;

    public static function handle(BoardMember $boardMember): void
    {
        $displayName = $boardMember->display_name;
        $photoPath = $boardMember->photo_path;

        DB::transaction(function () use ($boardMember, $displayName) {
            RecordActivity::run($boardMember, 'board_member_deleted', "Board member deleted: {$displayName}");

            if ($boardMember->user_id) {
                $user = $boardMember->user;
                if ($user) {
                    $user->update(['is_board_member' => false]);
                }
            }

            $boardMember->delete();
        });

        if ($photoPath) {
            Storage::disk(config('filesystems.public'))->delete($photoPath);
        }
    }
}
