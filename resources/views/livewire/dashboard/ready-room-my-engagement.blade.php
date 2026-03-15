<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\MeetingReport;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    #[\Livewire\Attributes\Computed]
    public function stats()
    {
        $userId = auth()->id();
        $ninetyDaysAgo = now()->subDays(90);

        $staffMeetingIds = Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->where('end_time', '>=', $ninetyDaysAgo)
            ->pluck('id');

        $totalMeetings = $staffMeetingIds->count();

        $attended = 0;
        $absent = 0;
        $reportsSubmitted = 0;

        if ($staffMeetingIds->isNotEmpty()) {
            $attended = DB::table('meeting_user')
                ->whereIn('meeting_id', $staffMeetingIds)
                ->where('user_id', $userId)
                ->where('attended', true)
                ->count();

            $absent = DB::table('meeting_user')
                ->whereIn('meeting_id', $staffMeetingIds)
                ->where('user_id', $userId)
                ->where('attended', false)
                ->count();

            $reportsSubmitted = MeetingReport::whereIn('meeting_id', $staffMeetingIds)
                ->where('user_id', $userId)
                ->whereNotNull('submitted_at')
                ->count();
        }

        return [
            'total_meetings' => $totalMeetings,
            'attended' => $attended,
            'absent' => $absent,
            'reports_submitted' => $reportsSubmitted,
        ];
    }
}; ?>

<flux:card>
    <flux:heading class="mb-4">My Engagement</flux:heading>

    @php $stats = $this->stats; @endphp

    @if($stats['total_meetings'] === 0)
        <flux:text variant="subtle" class="text-sm">No completed staff meetings in the last 90 days.</flux:text>
    @else
        <div class="space-y-3">
            <flux:text class="text-sm">Last 90 days ({{ $stats['total_meetings'] }} meetings)</flux:text>

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

            <flux:separator variant="subtle" />

            <div class="flex items-center justify-between">
                <flux:text class="text-sm">Staff Reports Submitted</flux:text>
                <flux:text class="text-sm font-semibold">{{ $stats['reports_submitted'] }} / {{ $stats['total_meetings'] }}</flux:text>
            </div>

            @if($stats['total_meetings'] - $stats['reports_submitted'] > 0)
                <flux:text variant="subtle" class="text-xs">{{ $stats['total_meetings'] - $stats['reports_submitted'] }} report(s) missed</flux:text>
            @endif
        </div>
    @endif
</flux:card>
