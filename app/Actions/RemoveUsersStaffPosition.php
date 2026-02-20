<?php

namespace App\Actions;

use App\Enums\StaffRank;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveUsersStaffPosition
{
    use AsAction;

    public function handle(User $user)
    {
        $updateText = 'Removed staff position: ';
        $updateText .= 'Department: '.$user->staff_department?->label();
        $updateText .= ', Rank: '.$user->staff_rank?->label();
        $updateText .= ', Title: '.$user->staff_title;

        // Remove the user's staff position
        $user->staff_department = null;
        $user->staff_rank = StaffRank::None;
        $user->staff_title = null;
        $user->save();

        \App\Actions\RecordActivity::run($user, 'staff_position_removed', $updateText);

        // Remove the MC staff rank for all active Minecraft accounts
        SyncMinecraftStaff::run($user);

        return true;
    }
}
