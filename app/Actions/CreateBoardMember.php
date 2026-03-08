<?php

namespace App\Actions;

use App\Models\BoardMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBoardMember
{
    use AsAction;

    public static function handle(
        string $displayName,
        ?string $title = null,
        ?int $userId = null,
        ?string $bio = null,
        ?string $photoPath = null,
        int $sortOrder = 0
    ): BoardMember {
        return DB::transaction(function () use ($displayName, $title, $userId, $bio, $photoPath, $sortOrder) {
            $boardMember = BoardMember::create([
                'display_name' => $displayName,
                'title' => $title,
                'user_id' => $userId,
                'bio' => $bio,
                'photo_path' => $photoPath,
                'sort_order' => $sortOrder,
            ]);

            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->update(['is_board_member' => true]);
                }
            }

            RecordActivity::run($boardMember, 'board_member_created', "Board member created: {$displayName}");

            return $boardMember;
        });
    }
}
