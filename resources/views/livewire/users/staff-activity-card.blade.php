<?php

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Thread;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public int $userId;

    public ?int $viewingReportMeetingId = null;

    public function mount(User $user): void
    {
        $this->authorize('view-staff-activity', $user);
        $this->userId = $user->id;
    }

    public function getUserProperty(): User
    {
        return User::findOrFail($this->userId);
    }

    private function getRecentStaffMeetings()
    {
        return Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->orderByDesc('scheduled_time')
            ->limit(7)
            ->with([
                'attendees' => fn ($q) => $q->where('users.id', $this->userId),
            ])
            ->get();
    }

    public function getMeetingRowsProperty(): \Illuminate\Support\Collection
    {
        $meetings = $this->getRecentStaffMeetings();

        $meetingIds = $meetings->pluck('id');

        $reports = MeetingReport::whereIn('meeting_id', $meetingIds)
            ->where('user_id', $this->userId)
            ->whereNotNull('submitted_at')
            ->with('answers.question')
            ->get()
            ->keyBy('meeting_id');

        return $meetings->map(function (Meeting $meeting) use ($reports) {
            $attendee = $meeting->attendees->first();
            $report = $reports->get($meeting->id);

            return [
                'meeting'   => $meeting,
                'attended'  => $attendee ? (bool) $attendee->pivot->attended : false,
                'on_record' => $attendee !== null,
                'report'    => $report,
            ];
        });
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

    public function viewReport(int $meetingId): void
    {
        $this->viewingReportMeetingId = $meetingId;
        Flux::modal('staff-report-modal-' . $this->userId)->show();
    }
}; ?>

<div>
    <flux:card class="w-full">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <flux:heading size="md">Staff Activity</flux:heading>
                <flux:badge color="zinc" size="sm">Last 7 meetings</flux:badge>
            </div>

            {{-- Tickets --}}
            <div class="flex items-center gap-2">
                <flux:text class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Tickets</flux:text>
                <flux:badge color="green" size="sm">{{ $this->openTickets }} open</flux:badge>
                <flux:badge color="zinc" size="sm">{{ $this->closedTickets }} closed</flux:badge>
            </div>
        </div>

        <flux:separator variant="subtle" class="my-2" />

        @if($this->meetingRows->isEmpty())
            <flux:text variant="subtle" class="text-center py-4">No completed staff meetings on record.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Meeting</flux:table.column>
                    <flux:table.column>Attendance</flux:table.column>
                    <flux:table.column>Staff Report</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->meetingRows as $row)
                        <flux:table.row wire:key="meeting-row-{{ $row['meeting']->id }}">
                            <flux:table.cell>
                                <div>
                                    <flux:text class="font-medium text-sm">{{ $row['meeting']->title }}</flux:text>
                                    <flux:text variant="subtle" class="text-xs">{{ $row['meeting']->scheduled_time->format('M j, Y') }}</flux:text>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if(! $row['on_record'])
                                    <flux:badge color="zinc" size="sm">Not on Record</flux:badge>
                                @elseif($row['attended'])
                                    <flux:badge color="green" size="sm">Attended</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Absent</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($row['report'])
                                    <flux:button size="sm" variant="ghost" wire:click="viewReport({{ $row['meeting']->id }})">View Report</flux:button>
                                @else
                                    <flux:text variant="subtle" class="text-sm">—</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    {{-- Staff Report Modal --}}
    <flux:modal name="staff-report-modal-{{ $this->userId }}" class="min-w-[32rem] !text-left">
        @if($viewingReportMeetingId)
            @php
                $viewRow = $this->meetingRows->firstWhere('meeting.id', $viewingReportMeetingId);
                $viewReport = $viewRow['report'] ?? null;
                $viewMeeting = $viewRow['meeting'] ?? null;
            @endphp

            @if($viewMeeting)
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ $viewMeeting->title }}</flux:heading>
                        <flux:text variant="subtle" class="text-sm">{{ $viewMeeting->scheduled_time->format('F j, Y') }}</flux:text>
                    </div>

                    @if($viewReport)
                        @foreach($viewReport->answers->sortBy(fn ($a) => $a->question->sort_order) as $answer)
                            <div wire:key="report-answer-{{ $answer->id }}">
                                <flux:text class="font-semibold text-sm">{{ $answer->question->question_text }}</flux:text>
                                @if($answer->answer)
                                    <div class="prose prose-sm dark:prose-invert max-w-none mt-1">
                                        {!! Str::markdown($answer->answer, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                    </div>
                                @else
                                    <flux:text class="mt-1" variant="subtle">No response</flux:text>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <flux:callout color="zinc">
                            <flux:callout.text>No staff update report submitted for this meeting.</flux:callout.text>
                        </flux:callout>
                    @endif

                    <div class="text-right">
                        <flux:modal.close>
                            <flux:button variant="ghost">Close</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            @endif
        @endif
    </flux:modal>
</div>
