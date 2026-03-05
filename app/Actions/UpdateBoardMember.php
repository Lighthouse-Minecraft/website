<?php

namespace App\Actions;

use App\Models\BoardMember;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBoardMember
{
    use AsAction;

    public static function handle(
        BoardMember $boardMember,
        string $displayName,
        ?string $title = null,
        ?string $bio = null,
        ?string $photoPath = null,
        int $sortOrder = 0
    ): void {
        $boardMember->update([
            'display_name' => $displayName,
            'title' => $title,
            'bio' => $bio,
            'photo_path' => $photoPath,
            'sort_order' => $sortOrder,
        ]);

        RecordActivity::run($boardMember, 'board_member_updated', "Board member updated: {$displayName}");
    }
}
