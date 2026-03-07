<?php

use Livewire\Volt\Component;
use App\Models\Meeting;
use App\Models\MeetingReport;

new class extends Component {
    public $meetings;
    public array $userReports = [];

    public function mount() {
        $this->meetings = Meeting::where('status', 'pending')
            ->withCount('questions')
            ->orderBy('scheduled_time', 'asc')
            ->take(3)
            ->get();

        if (auth()->check()) {
            $meetingIds = $this->meetings->pluck('id');
            $reports = MeetingReport::where('user_id', auth()->id())
                ->whereIn('meeting_id', $meetingIds)
                ->whereNotNull('submitted_at')
                ->pluck('meeting_id')
                ->toArray();

            $this->userReports = $reports;
        }
    }
}; ?>

<div class="space-y-6">
    <flux:heading>Upcoming Meetings</flux:heading>
    <ul>
        @foreach($meetings as $meeting)
            <li wire:key="upcoming-meeting-{{ $meeting->id }}" class="my-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <flux:link href="{{ route('meeting.edit', $meeting) }}">
                            {{ $meeting->title }}
                        </flux:link>
                        <flux:text variant="subtle" class="text-xs">{{ $meeting->scheduled_time->setTimezone('America/New_York')->format('m/d/Y \@ g:i a') }} ET</flux:text>
                    </div>

                    @if($meeting->isStaffMeeting() && $meeting->questions_count > 0)
                        @if($meeting->isReportUnlocked())
                            @if(in_array($meeting->id, $userReports))
                                <flux:button href="{{ route('meeting.report', $meeting) }}" variant="ghost" size="xs">
                                    Update Check-In
                                </flux:button>
                            @else
                                <flux:button href="{{ route('meeting.report', $meeting) }}" variant="primary" size="xs">
                                    Submit Check-In
                                </flux:button>
                            @endif
                        @else
                            <flux:button variant="ghost" size="xs" disabled class="opacity-50">
                                Check-In
                            </flux:button>
                        @endif
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
