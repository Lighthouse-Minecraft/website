<?php

namespace App\Actions;

use App\Enums\StaffRank;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AssignStaffPosition
{
    use AsAction;

    public function handle(StaffPosition $position, User $user): void
    {
        DB::transaction(function () use ($position, $user) {
            // If user already holds a different position, unassign them properly
            $existingPosition = StaffPosition::where('user_id', $user->id)->first();
            if ($existingPosition && $existingPosition->id !== $position->id) {
                UnassignStaffPosition::run($existingPosition);
            }

            // If this position already has a different user, unassign them
            if ($position->user_id && $position->user_id !== $user->id) {
                UnassignStaffPosition::run($position);
            }

            // Assign user to the position
            $position->update(['user_id' => $user->id]);

            // Determine effective rank based on age
            $effectiveRank = $this->computeEffectiveRank($position->rank, $user);

            // Sync the user's staff fields via existing action
            SetUsersStaffPosition::run(
                $user,
                $position->title,
                $position->department,
                $effectiveRank
            );

            RecordActivity::run(
                $position,
                'staff_position_assigned',
                "Assigned {$user->name} to position: {$position->title} ({$position->department->label()}, {$effectiveRank->label()})"
            );
        });
    }

    private function computeEffectiveRank(StaffRank $positionRank, User $user): StaffRank
    {
        if ($positionRank === StaffRank::CrewMember && $user->age() !== null && $user->age() < 17) {
            return StaffRank::JrCrew;
        }

        return $positionRank;
    }
}
