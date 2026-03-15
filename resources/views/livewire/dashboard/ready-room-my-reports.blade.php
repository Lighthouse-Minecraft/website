<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\MeetingReport;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $viewingMeetingId = null;

    #[\Livewire\Attributes\Computed]
    public function recentMeetings()
    {
        return Meeting::where('type', MeetingType::StaffMeeting)
            ->whereIn('status', [MeetingStatus::Completed, MeetingStatus::Finalizing])
            ->orderBy('end_time', 'desc')
            ->take(6)
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function userReportIds()
    {
        $meetingIds = $this->recentMeetings->pluck('id');

        return MeetingReport::where('user_id', auth()->id())
            ->whereIn('meeting_id', $meetingIds)
            ->whereNotNull('submitted_at')
            ->pluck('meeting_id')
            ->toArray();
    }

    #[\Livewire\Attributes\Computed]
    public function viewingReport()
    {
        if (! $this->viewingMeetingId) {
            return null;
        }

        return MeetingReport::where('meeting_id', $this->viewingMeetingId)
            ->where('user_id', auth()->id())
            ->whereNotNull('submitted_at')
            ->with(['answers.question'])
            ->first();
    }

    public function viewReport(int $meetingId): void
    {
        $this->viewingMeetingId = $meetingId;
        unset($this->viewingReport);
        Flux::modal('view-my-report')->show();
    }
}; ?>

<flux:card>
    <flux:heading class="mb-4">My Staff Reports</flux:heading>

    @if($this->recentMeetings->isEmpty())
        <flux:text variant="subtle" class="text-sm">No recent staff meetings.</flux:text>
    @else
        <div class="grid grid-cols-2 gap-2">
            @foreach($this->recentMeetings as $meeting)
                @php $hasSubmitted = in_array($meeting->id, $this->userReportIds); @endphp
                <flux:button
                    wire:key="report-btn-{{ $meeting->id }}"
                    wire:click="viewReport({{ $meeting->id }})"
                    size="sm"
                    :variant="$hasSubmitted ? 'filled' : 'ghost'"
                    :disabled="! $hasSubmitted"
                    :class="! $hasSubmitted ? 'pointer-events-none opacity-50' : ''"
                    class="w-full justify-center"
                >
                    {{ \Carbon\Carbon::parse($meeting->day)->format('M j, Y') }}
                    @if($hasSubmitted)
                        <flux:icon name="check" variant="micro" class="ml-1" />
                    @endif
                </flux:button>
            @endforeach
        </div>

        <flux:text variant="subtle" class="text-xs mt-3">Click a meeting to view your submitted report.</flux:text>
    @endif

    <flux:modal name="view-my-report" class="min-w-[32rem] !text-left">
        @if($this->viewingReport)
            @php $report = $this->viewingReport; @endphp
            <div class="space-y-4">
                <flux:heading size="lg">My Staff Update Report</flux:heading>
                <flux:text variant="subtle" class="text-sm">
                    {{ $report->meeting->title }} &mdash; {{ \Carbon\Carbon::parse($report->meeting->day)->format('M j, Y') }}
                </flux:text>

                @foreach($report->answers->sortBy(fn ($a) => $a->question->sort_order) as $answer)
                    <div>
                        <flux:text class="font-semibold text-sm">{{ $answer->question->question_text }}</flux:text>
                        <flux:text class="mt-1">{{ $answer->answer ?: 'No response' }}</flux:text>
                    </div>
                @endforeach

                <div class="text-right">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @elseif($viewingMeetingId)
            <div class="space-y-4">
                <flux:heading size="lg">No Report Found</flux:heading>
                <flux:text variant="subtle">You did not submit a report for this meeting.</flux:text>
                <div class="text-right">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</flux:card>
