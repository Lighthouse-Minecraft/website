<?php

namespace App\Actions;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReportAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        if (! $meeting->isReportUnlocked()) {
            throw new \InvalidArgumentException('The report window is not open yet.');
        }

        if (! $meeting->questions()->exists()) {
            throw new \InvalidArgumentException('This meeting has no questions to answer.');
        }

        $validQuestionIds = $meeting->questions()->pluck('id')->all();

        return DB::transaction(function () use ($meeting, $user, $answers, $validQuestionIds) {
            $report = MeetingReport::updateOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $user->id],
                ['submitted_at' => now()]
            );

            RecordActivity::run($report, 'submit_meeting_report', 'Submitted or updated meeting report.');

            foreach ($answers as $questionId => $answerText) {
                if (! in_array($questionId, $validQuestionIds)) {
                    continue;
                }

                MeetingReportAnswer::updateOrCreate(
                    ['meeting_report_id' => $report->id, 'meeting_question_id' => $questionId],
                    ['answer' => $answerText]
                );
            }

            return $report;
        });
    }
}
