<?php

namespace App\Actions;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReportAnswer;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitMeetingReport
{
    use AsAction;

    public function handle(Meeting $meeting, User $user, array $answers): MeetingReport
    {
        if (! $meeting->isStaffMeeting()) {
            throw new \InvalidArgumentException('Reports can only be submitted for staff meetings.');
        }

        if ($meeting->isReportLocked()) {
            throw new \InvalidArgumentException('Reports cannot be submitted after the meeting has started.');
        }

        $report = MeetingReport::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $user->id],
            ['submitted_at' => now()]
        );

        foreach ($answers as $questionId => $answerText) {
            MeetingReportAnswer::updateOrCreate(
                ['meeting_report_id' => $report->id, 'meeting_question_id' => $questionId],
                ['answer' => $answerText]
            );
        }

        return $report;
    }
}
