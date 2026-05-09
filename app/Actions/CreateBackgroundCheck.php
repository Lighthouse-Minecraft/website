<?php

namespace App\Actions;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBackgroundCheck
{
    use AsAction;

    public function handle(
        User $user,
        User $runBy,
        string $service,
        Carbon $completedDate,
        ?string $notes = null,
    ): BackgroundCheck {
        if ($completedDate->isFuture()) {
            throw new \InvalidArgumentException('Completed date cannot be in the future.');
        }

        $check = BackgroundCheck::create([
            'user_id' => $user->id,
            'run_by_user_id' => $runBy->id,
            'service' => $service,
            'completed_date' => $completedDate,
            'status' => BackgroundCheckStatus::Pending,
            'notes' => $notes,
        ]);

        RecordActivity::run(
            $check,
            'background_check_created',
            "Background check created for {$user->name} by {$runBy->name} using service \"{$service}\".",
            $runBy,
        );

        return $check;
    }
}
