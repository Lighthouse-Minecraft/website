<?php

namespace App\Actions;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBackgroundCheckStatus
{
    use AsAction;

    public function handle(BackgroundCheck $check, BackgroundCheckStatus $newStatus, User $updatedBy): void
    {
        if ($check->status->isTerminal()) {
            throw new \InvalidArgumentException('Cannot change the status of a locked background check.');
        }

        $isTransitioningToTerminal = $newStatus->isTerminal();

        $check->status = $newStatus;

        if ($isTransitioningToTerminal) {
            $check->locked_at = now();
        }

        $check->save();

        RecordActivity::run(
            $check,
            'background_check_status_updated',
            "Background check status updated to {$newStatus->label()} by {$updatedBy->name}.",
            $updatedBy,
        );
    }
}
