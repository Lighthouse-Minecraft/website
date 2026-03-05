<?php

use App\Actions\SubmitMeetingReport;
use App\Models\Meeting;
use App\Models\MeetingReport;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public array $answers = [];
    public bool $hasSubmitted = false;

    public function mount(Meeting $meeting): void
    {
        $this->authorize('view-ready-room');

        if (! $meeting->isStaffMeeting()) {
            abort(404);
        }

        $this->meeting = $meeting->load('questions');

        $existingReport = MeetingReport::where('meeting_id', $meeting->id)
            ->where('user_id', auth()->id())
            ->with('answers')
            ->first();

        if ($existingReport && $existingReport->isSubmitted()) {
            $this->hasSubmitted = true;
            foreach ($existingReport->answers as $answer) {
                $this->answers[$answer->meeting_question_id] = $answer->answer ?? '';
            }
        }

        foreach ($meeting->questions as $question) {
            if (! isset($this->answers[$question->id])) {
                $this->answers[$question->id] = '';
            }
        }
    }

    public function submitReport(): void
    {
        $this->authorize('view-ready-room');

        if ($this->meeting->isReportLocked()) {
            Flux::toast('Reports are locked — the meeting has already started.', variant: 'danger');
            return;
        }

        if (! $this->meeting->isReportUnlocked()) {
            Flux::toast('The report window is not open yet.', variant: 'danger');
            return;
        }

        SubmitMeetingReport::run($this->meeting, auth()->user(), $this->answers);

        $this->hasSubmitted = true;
        Flux::toast('Report submitted successfully!', variant: 'success');
    }
}; ?>

<x-layouts.app>
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Pre-Meeting Report</flux:heading>
            <flux:button href="{{ route('ready-room.index') }}" variant="ghost" icon="arrow-left">
                Back to Ready Room
            </flux:button>
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-2">{{ $meeting->title }}</flux:heading>
            <flux:text variant="subtle">
                {{ $meeting->scheduled_time->setTimezone('America/New_York')->format('F j, Y g:i A') }} ET
            </flux:text>
        </flux:card>

        @if($meeting->isReportLocked())
            <flux:callout color="amber">
                <flux:callout.heading>Reports Locked</flux:callout.heading>
                <flux:callout.text>The meeting has started. Reports can no longer be submitted or updated.</flux:callout.text>
            </flux:callout>

            @if($hasSubmitted)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Your Submitted Report</flux:heading>
                    @foreach($meeting->questions as $question)
                        <div class="mb-4">
                            <flux:text class="font-semibold text-sm">{{ $question->question_text }}</flux:text>
                            <flux:text class="mt-1">
                                {{ $answers[$question->id] ?: 'No response' }}
                            </flux:text>
                        </div>
                    @endforeach
                </flux:card>
            @else
                <flux:callout color="zinc">
                    <flux:callout.text>You did not submit a report for this meeting.</flux:callout.text>
                </flux:callout>
            @endif
        @elseif(! $meeting->isReportUnlocked())
            <flux:callout color="zinc">
                <flux:callout.heading>Report Window Not Open</flux:callout.heading>
                <flux:callout.text>
                    The report form opens {{ config('lighthouse.meeting_report_unlock_days', 7) }} days before the meeting.
                </flux:callout.text>
            </flux:callout>
        @else
            <form wire:submit="submitReport" class="space-y-6">
                @foreach($meeting->questions as $question)
                    <flux:textarea
                        wire:model="answers.{{ $question->id }}"
                        label="{{ $question->question_text }}"
                        rows="4"
                    />
                @endforeach

                <div class="text-right">
                    <flux:button type="submit" variant="primary">
                        {{ $hasSubmitted ? 'Update Report' : 'Submit Report' }}
                    </flux:button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.app>
