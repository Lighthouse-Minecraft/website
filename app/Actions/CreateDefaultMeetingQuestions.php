<?php

namespace App\Actions;

use App\Models\Meeting;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDefaultMeetingQuestions
{
    use AsAction;

    public function handle(Meeting $meeting): void
    {
        if (! $meeting->isStaffMeeting()) {
            return;
        }

        if ($meeting->questions()->exists()) {
            return;
        }

        $defaults = [
            'What did I accomplish this iteration (since the last meeting)?',
            'What am I currently working on?',
            'What do I plan on working on in the next iteration?',
            'What help do I need from my department or the staff team?',
        ];

        foreach ($defaults as $i => $question) {
            $meeting->questions()->create([
                'question_text' => $question,
                'sort_order' => $i,
            ]);
        }

        RecordActivity::run($meeting, 'seed_default_questions', 'Seeded default meeting questions.');
    }
}
