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
        ?int $sortOrder = null
    ): void {
        $data = [
            'display_name' => $displayName,
            'title' => $title,
            'bio' => $bio,
            'photo_path' => $photoPath,
        ];

        if ($sortOrder !== null) {
            $data['sort_order'] = $sortOrder;
        }

        $boardMember->update($data);

        RecordActivity::run($boardMember, 'board_member_updated', "Board member updated: {$displayName}");
    }
}
