<?php

namespace App\Actions;

use App\Models\StaffPosition;
use Lorisleiva\Actions\Concerns\AsAction;

class UnassignStaffPosition
{
    use AsAction;

    public function handle(StaffPosition $position): void
    {
        $user = $position->user;

        if (! $user) {
            return;
        }

        $positionTitle = $position->title;

        // Clear the position assignment
        $position->update(['user_id' => null]);

        // Remove user's staff position fields via existing action
        RemoveUsersStaffPosition::run($user);

        RecordActivity::run(
            $position,
            'staff_position_unassigned',
            "Unassigned {$user->name} from position: {$positionTitle}"
        );
    }
}
