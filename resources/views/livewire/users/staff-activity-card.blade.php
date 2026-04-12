<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffRank;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Thread;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public int $userId;

    public function mount(User $user): void
    {
        $this->authorize('view-staff-activity', $user);
        $this->userId = $user->id;
    }

    public function getUserProperty(): User
    {
        return User::findOrFail($this->userId);
    }

    private function getThreeMonthsAgo(): \Illuminate\Support\Carbon
    {
        return now()->subMonths(3);
    }

    private function getStaffMeetings3mo()
    {
        return Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->where('end_time', '>=', $this->getThreeMonthsAgo())
            ->get();
    }

    public function getMeetingAttendanceProperty(): \Illuminate\Support\Collection
    {
        return $this->getStaffMeetings3mo()
            ->map(function (Meeting $meeting) {
                $pivot = DB::table('meeting_user')
                    ->where('meeting_id', $meeting->id)
                    ->where('user_id', $this->userId)
                    ->first();

                return [
                    'meeting' => $meeting,
                    'attended' => $pivot ? (bool) $pivot->attended : false,
                    'on_record' => $pivot !== null,
                ];
            })
            ->sortByDesc(fn ($row) => $row['meeting']->scheduled_time);
    }

    public function getAttendanceCountProperty(): int
    {
        return DB::table('meeting_user')
            ->join('meetings', 'meetings.id', '=', 'meeting_user.meeting_id')
            ->where('meeting_user.user_id', $this->userId)
            ->where('meeting_user.attended', true)
            ->where('meetings.type', MeetingType::StaffMeeting->value)
            ->where('meetings.status', MeetingStatus::Completed->value)
            ->where('meetings.end_time', '>=', $this->getThreeMonthsAgo())
            ->count();
    }

    public function getTotalMeetings3moProperty(): int
    {
        return Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->where('end_time', '>=', $this->getThreeMonthsAgo())
            ->count();
    }

    public function getReportsFiledProperty(): int
    {
        $meetingIds = Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->where('end_time', '>=', $this->getThreeMonthsAgo())
            ->pluck('id');

        return MeetingReport::whereIn('meeting_id', $meetingIds)
            ->where('user_id', $this->userId)
            ->whereNotNull('submitted_at')
            ->count();
    }

    public function getReportsMissedProperty(): int
    {
        return max(0, $this->totalMeetings3mo - $this->reportsFiled);
    }

    public function getOpenTicketsProperty(): int
    {
        return Thread::where('type', ThreadType::Ticket)
            ->where('assigned_to_user_id', $this->userId)
            ->whereIn('status', [ThreadStatus::Open, ThreadStatus::Pending])
            ->count();
    }

    public function getClosedTicketsProperty(): int
    {
        return Thread::where('type', ThreadType::Ticket)
            ->where('assigned_to_user_id', $this->userId)
            ->whereIn('status', [ThreadStatus::Resolved, ThreadStatus::Closed])
            ->count();
    }

    public function openAttendanceModal(): void
    {
        Flux::modal('staff-attendance-modal-' . $this->userId)->show();
    }
}; ?>

<div>
    <flux:card class="w-full">
        <div class="flex items-center gap-3">
            <flux:heading size="md">Staff Activity</flux:heading>
            <flux:badge color="zinc" size="sm">Last 3 months</flux:badge>
        </div>

        <flux:separator variant="subtle" class="my-2" />

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Meeting Attendance --}}
            <div class="flex flex-col gap-1">
                <flux:text class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Meeting Attendance</flux:text>
                <div class="flex items-center gap-2">
                    <button wire:click="openAttendanceModal" class="text-lg font-bold text-blue-600 dark:text-blue-400 hover:underline focus:outline-none">
                        {{ $this->attendanceCount }} / {{ $this->totalMeetings3mo }}
                    </button>
                    <flux:text variant="subtle" class="text-sm">meetings</flux:text>
                </div>
            </div>

            {{-- Staff Reports --}}
            <div class="flex flex-col gap-1">
                <flux:text class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Staff Reports</flux:text>
                <div class="flex items-center gap-2">
                    <span class="text-lg font-bold text-zinc-800 dark:text-zinc-200">
                        {{ $this->reportsFiled }} filed
                    </span>
                    @if($this->reportsMissed > 0)
                        <flux:badge color="amber" size="sm">{{ $this->reportsMissed }} missed</flux:badge>
                    @endif
                </div>
            </div>

            {{-- Tickets --}}
            <div class="flex flex-col gap-1">
                <flux:text class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Tickets</flux:text>
                <div class="flex items-center gap-2">
                    <flux:badge color="green" size="sm">{{ $this->openTickets }} open</flux:badge>
                    <flux:badge color="zinc" size="sm">{{ $this->closedTickets }} closed</flux:badge>
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Attendance Modal --}}
    <flux:modal name="staff-attendance-modal-{{ $this->userId }}" class="w-full md:w-1/2">
        <div class="space-y-4">
            <flux:heading size="lg">Meeting Attendance</flux:heading>
            <flux:text variant="subtle">Staff meetings in the last 3 months</flux:text>

            @if($this->meetingAttendance->isEmpty())
                <flux:text variant="subtle" class="text-center py-4">No completed staff meetings in the last 3 months.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Meeting</flux:table.column>
                        <flux:table.column>Date</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->meetingAttendance as $row)
                            <flux:table.row wire:key="attendance-{{ $row['meeting']->id }}">
                                <flux:table.cell>{{ $row['meeting']->title }}</flux:table.cell>
                                <flux:table.cell>{{ $row['meeting']->scheduled_time->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if(! $row['on_record'])
                                        <flux:badge color="zinc" size="sm">Not on Record</flux:badge>
                                    @elseif($row['attended'])
                                        <flux:badge color="green" size="sm">Attended</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">Absent</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </flux:modal>
</div>
