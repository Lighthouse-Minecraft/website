<?php

namespace App\Actions;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SetUsersStaffPosition
{
    use AsAction;

    public function handle(User $user, $title, StaffDepartment $department, StaffRank $rank)
    {
        // Update the user's staff position
        if ($title == null || $title === '') {
            return false;
        }

        $existingDepartment = $user->staff_department;
        $existingRank = $user->staff_rank;
        $existingTitle = $user->staff_title;

        $updateText = 'Updating staff position: ';

        if ($existingDepartment !== $department) {
            $updateText .= 'Department: '.$existingDepartment?->label().' => '.$department->label().', ';
            $user->staff_department = $department;
        }

        if ($existingRank !== $rank) {
            $updateText .= 'Rank: '.($existingRank ? $existingRank->label() : 'None').' => '.$rank->label().', ';
            $user->staff_rank = $rank;
        }

        if ($existingTitle !== $title) {
            $updateText .= 'Title: '.$existingTitle.' => '.$title;
            $user->staff_title = $title;
        }

        if ($existingDepartment === $department && $existingRank === $rank && $existingTitle === $title) {
            return true; // No changes needed
        }

        $user->save();

        \App\Actions\RecordActivity::run($user, 'staff_position_updated', $updateText);

        return true;
    }
}
