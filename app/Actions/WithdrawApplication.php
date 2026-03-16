<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Models\StaffApplication;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class WithdrawApplication
{
    use AsAction;

    public function handle(StaffApplication $application, User $applicant): void
    {
        if ($applicant->id !== $application->user_id) {
            throw new \RuntimeException('You can only withdraw your own application.');
        }

        if ($application->isTerminal()) {
            throw new \RuntimeException('This application has already been finalized and cannot be withdrawn.');
        }

        $application->update(['status' => ApplicationStatus::Withdrawn]);

        RecordActivity::run(
            $application,
            'application_withdrawn',
            "{$applicant->name} withdrew application for {$application->staffPosition->title}.",
        );
    }
}
