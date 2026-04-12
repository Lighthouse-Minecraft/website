<?php

namespace App\Actions;

use App\Models\StaffPosition;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($position, $user, $positionTitle) {
            // Flag credentials accessed by the departing user before clearing the position
            FlagCredentialsAfterPositionRemoval::run($user, $position);

            // Clear the position assignment
            $position->update(['user_id' => null]);

            // Remove user's staff position fields via existing action
            RemoveUsersStaffPosition::run($user);

            RecordActivity::run(
                $position,
                'staff_position_unassigned',
                "Unassigned {$user->name} from position: {$positionTitle}"
            );
        });
    }
}
