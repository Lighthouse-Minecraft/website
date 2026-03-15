<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\TaskStatus;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Task;
use App\Models\Thread;
use Livewire\Volt\Component;

new class extends Component {
    #[\Livewire\Attributes\Computed]
    public function stats()
    {
        $user = auth()->user();
        $ninetyDaysAgo = now()->subDays(90);

        $staffMeetingIds = Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->where('end_time', '>=', $ninetyDaysAgo)
            ->pluck('id');

        $attended = 0;
        $absent = 0;
        $totalMeetings = 0;
        $reportsSubmitted = 0;

        if ($staffMeetingIds->isNotEmpty()) {
            $userMeetings = $user->belongsToMany(Meeting::class)
                ->withPivot('attended')
                ->whereIn('meetings.id', $staffMeetingIds)
                ->get();

            $totalMeetings = $userMeetings->count();
            $attended = $userMeetings->where('pivot.attended', true)->count();
            $absent = $userMeetings->where('pivot.attended', false)->count();

            $reportsSubmitted = MeetingReport::whereIn('meeting_id', $staffMeetingIds)
                ->where('user_id', $user->id)
                ->whereNotNull('submitted_at')
                ->count();
        }

        $tasksCompleted = Task::where('assigned_to_user_id', $user->id)
            ->where('status', TaskStatus::Completed)
            ->where('completed_at', '>=', $ninetyDaysAgo)
            ->count();

        $ticketsCompleted = Thread::where('type', ThreadType::Ticket)
            ->where('assigned_to_user_id', $user->id)
            ->where('status', ThreadStatus::Closed)
            ->where('updated_at', '>=', $ninetyDaysAgo)
            ->count();

        return [
            'total_meetings' => $totalMeetings,
            'attended' => $attended,
            'absent' => $absent,
            'reports_submitted' => $reportsSubmitted,
            'tasks_completed' => $tasksCompleted,
            'tickets_completed' => $ticketsCompleted,
        ];
    }
}; ?>

<flux:card>
    <flux:heading class="mb-4">My Engagement</flux:heading>

    @php $stats = $this->stats; @endphp

    <div class="space-y-3">
        <flux:text class="text-sm">Last 90 days</flux:text>

        @if($stats['total_meetings'] > 0)
            <div class="flex items-center justify-between">
                <flux:text class="text-sm">Meetings Attended</flux:text>
                <flux:badge size="sm" color="green">{{ $stats['attended'] }}</flux:badge>
            </div>

            <div class="flex items-center justify-between">
                <flux:text class="text-sm">Meetings Absent</flux:text>
                @if($stats['absent'] > 0)
                    <flux:badge size="sm" color="red">{{ $stats['absent'] }}</flux:badge>
                @else
                    <flux:badge size="sm" color="green">0</flux:badge>
                @endif
            </div>

            <div class="flex items-center justify-between">
                <flux:text class="text-sm">Staff Reports Submitted</flux:text>
                <flux:text class="text-sm font-semibold">{{ $stats['reports_submitted'] }} / {{ $stats['total_meetings'] }}</flux:text>
            </div>

            @if($stats['total_meetings'] - $stats['reports_submitted'] > 0)
                <flux:text variant="subtle" class="text-xs">{{ $stats['total_meetings'] - $stats['reports_submitted'] }} report(s) missed</flux:text>
            @endif

            <flux:separator variant="subtle" />
        @endif

        <div class="flex items-center justify-between">
            <flux:text class="text-sm">Tasks Completed</flux:text>
            <flux:badge size="sm" color="green">{{ $stats['tasks_completed'] }}</flux:badge>
        </div>

        <div class="flex items-center justify-between">
            <flux:text class="text-sm">Tickets Completed</flux:text>
            <flux:badge size="sm" color="green">{{ $stats['tickets_completed'] }}</flux:badge>
        </div>
    </div>
</flux:card>
