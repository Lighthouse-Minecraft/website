<?php

use App\Actions\GetIterationBoundaries;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffRank;
use App\Enums\TaskStatus;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Task;
use App\Models\Thread;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Locked]
    public ?int $selectedStaffId = null;

    public function getStaffTableProperty()
    {
        $boundaries = GetIterationBoundaries::run();
        $cs = $boundaries['current_start'];
        $ce = $boundaries['current_end'];
        $ps = $boundaries['previous_start'];
        $pe = $boundaries['previous_end'];
        $hasPrevious = $boundaries['has_previous'];
        $threeMonthsAgo = now()->subMonths(3);

        $staffUsers = User::where('staff_rank', '!=', StaffRank::None)
            ->orderByRaw('CAST(staff_rank AS INTEGER) DESC')
            ->orderBy('name')
            ->paginate(15, pageName: 'staff-page');

        $staffIds = $staffUsers->pluck('id');

        // Batch-load current iteration todos completed
        $currentTaskCompleted = Task::whereIn('assigned_to_user_id', $staffIds)
            ->where('status', TaskStatus::Completed)
            ->whereBetween('completed_at', [$cs, $ce])
            ->selectRaw('assigned_to_user_id, COUNT(*) as count')
            ->groupBy('assigned_to_user_id')
            ->pluck('count', 'assigned_to_user_id');

        // Previous iteration todos completed
        $previousTaskCompleted = collect();
        if ($hasPrevious) {
            $previousTaskCompleted = Task::whereIn('assigned_to_user_id', $staffIds)
                ->where('status', TaskStatus::Completed)
                ->whereBetween('completed_at', [$ps, $pe])
                ->selectRaw('assigned_to_user_id, COUNT(*) as count')
                ->groupBy('assigned_to_user_id')
                ->pluck('count', 'assigned_to_user_id');
        }

        // Batch-load open todos (point-in-time)
        $currentTodosOpen = Task::whereIn('assigned_to_user_id', $staffIds)
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Archived])
            ->selectRaw('assigned_to_user_id, COUNT(*) as count')
            ->groupBy('assigned_to_user_id')
            ->pluck('count', 'assigned_to_user_id');

        // Batch-load current iteration ticket stats
        $currentTicketsWorked = Thread::where('type', ThreadType::Ticket)
            ->whereIn('assigned_to_user_id', $staffIds)
            ->where('status', ThreadStatus::Closed)
            ->whereBetween('updated_at', [$cs, $ce])
            ->selectRaw('assigned_to_user_id, COUNT(*) as count')
            ->groupBy('assigned_to_user_id')
            ->pluck('count', 'assigned_to_user_id');

        $currentTicketsOpen = Thread::where('type', ThreadType::Ticket)
            ->whereIn('assigned_to_user_id', $staffIds)
            ->whereNotIn('status', [ThreadStatus::Closed])
            ->selectRaw('assigned_to_user_id, COUNT(*) as count')
            ->groupBy('assigned_to_user_id')
            ->pluck('count', 'assigned_to_user_id');

        // Previous iteration ticket stats
        $previousTicketsWorked = collect();
        if ($hasPrevious) {
            $previousTicketsWorked = Thread::where('type', ThreadType::Ticket)
                ->whereIn('assigned_to_user_id', $staffIds)
                ->where('status', ThreadStatus::Closed)
                ->whereBetween('updated_at', [$ps, $pe])
                ->selectRaw('assigned_to_user_id, COUNT(*) as count')
                ->groupBy('assigned_to_user_id')
                ->pluck('count', 'assigned_to_user_id');
        }

        // Last 3 months: reports and attendance
        $staffMeetings3mo = Meeting::where('type', MeetingType::StaffMeeting)
            ->where('status', MeetingStatus::Completed)
            ->where('end_time', '>=', $threeMonthsAgo)
            ->pluck('id');

        $totalMeetings3mo = $staffMeetings3mo->count();

        $reportsSubmitted = collect();
        $meetingsAttended = collect();
        if ($staffMeetings3mo->isNotEmpty()) {
            $reportsSubmitted = MeetingReport::whereIn('meeting_id', $staffMeetings3mo)
                ->whereIn('user_id', $staffIds)
                ->whereNotNull('submitted_at')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->pluck('count', 'user_id');

            $meetingsAttended = DB::table('meeting_user')
                ->whereIn('meeting_id', $staffMeetings3mo)
                ->whereIn('user_id', $staffIds)
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->pluck('count', 'user_id');
        }

        $staffData = $staffUsers->through(function ($user) use (
            $currentTaskCompleted, $previousTaskCompleted, $currentTodosOpen,
            $currentTicketsWorked, $currentTicketsOpen, $previousTicketsWorked,
            $reportsSubmitted, $totalMeetings3mo,
            $meetingsAttended, $hasPrevious
        ) {
            $reportsMissed = $totalMeetings3mo - $reportsSubmitted->get($user->id, 0);
            $meetingsMissed = $totalMeetings3mo - $meetingsAttended->get($user->id, 0);

            return [
                'user' => $user,
                'todos_worked' => $currentTaskCompleted->get($user->id, 0),
                'todos_worked_prev' => $hasPrevious ? $previousTaskCompleted->get($user->id, 0) : null,
                'todos_open' => $currentTodosOpen->get($user->id, 0),
                'tickets_worked' => $currentTicketsWorked->get($user->id, 0),
                'tickets_worked_prev' => $hasPrevious ? $previousTicketsWorked->get($user->id, 0) : null,
                'tickets_open' => $currentTicketsOpen->get($user->id, 0),
                'reports_submitted' => $reportsSubmitted->get($user->id, 0),
                'reports_missed' => $reportsMissed,
                'meetings_attended' => $meetingsAttended->get($user->id, 0),
                'meetings_missed' => $user->staff_rank->value >= StaffRank::CrewMember->value ? $meetingsMissed : null,
                'is_jrcrew' => $user->staff_rank === StaffRank::JrCrew,
                'is_officer' => $user->staff_rank === StaffRank::Officer,
                'total_meetings_3mo' => $totalMeetings3mo,
            ];
        });

        return $staffData;
    }

    public function getStaffDetailProperty(): ?array
    {
        if (! $this->selectedStaffId) {
            return null;
        }

        $user = User::where('staff_rank', '!=', StaffRank::None)
            ->findOrFail($this->selectedStaffId);
        $boundaries = GetIterationBoundaries::run();
        $iterations = $boundaries['iterations_3mo'];

        $detail = [];
        foreach ($iterations as $iter) {
            $start = $iter['start'];
            $end = $iter['end'];
            $meeting = $iter['meeting'];

            $completed = Task::where('assigned_to_user_id', $user->id)
                ->where('status', TaskStatus::Completed)
                ->whereBetween('completed_at', [$start, $end])
                ->count();

            $ticketsWorked = Thread::where('type', ThreadType::Ticket)
                ->where('assigned_to_user_id', $user->id)
                ->where('status', ThreadStatus::Closed)
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $reportSubmitted = $meeting
                ? MeetingReport::where('meeting_id', $meeting->id)
                    ->where('user_id', $user->id)
                    ->whereNotNull('submitted_at')
                    ->exists()
                : null;

            $attended = $meeting
                ? $meeting->attendees()->where('users.id', $user->id)->exists()
                : null;

            $detail[] = [
                'label' => $start->format('M j') . ' - ' . $end->format('M j'),
                'completed' => $completed,
                'tickets_worked' => $ticketsWorked,
                'report_submitted' => $reportSubmitted,
                'attended' => $attended,
            ];
        }

        return [
            'user' => $user,
            'iterations' => $detail,
        ];
    }

    public function viewStaffDetail(int $userId): void
    {
        $this->authorize('view-command-dashboard');

        // Verify the target user is actually staff
        User::where('staff_rank', '!=', StaffRank::None)->findOrFail($userId);

        $this->selectedStaffId = $userId;
        Flux::modal('staff-detail-modal')->show();
    }
}; ?>

<div>
<flux:card class="w-full">
    <flux:heading size="md" class="mb-4">Staff Engagement</flux:heading>
    <flux:separator variant="subtle" class="mb-4" />

    @php $staffTable = $this->staffTable; @endphp

    <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Dept</flux:table.column>
                <flux:table.column>Rank</flux:table.column>
                <flux:table.column>Todos Worked</flux:table.column>
                <flux:table.column>Todos Open</flux:table.column>
                <flux:table.column>Tickets Worked</flux:table.column>
                <flux:table.column>Tickets Open</flux:table.column>
                <flux:table.column>Reports (3mo)</flux:table.column>
                <flux:table.column>Attendance (3mo)</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($staffTable as $entry)
                    @php $user = $entry['user']; @endphp
                    <flux:table.row wire:key="staff-{{ $user->id }}">
                        <flux:table.cell>
                            <flux:link href="{{ route('profile.show', $user) }}">{{ $user->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($user->staff_department)
                                <flux:badge size="sm">{{ $user->staff_department->label() }}</flux:badge>
                            @else
                                <flux:text variant="subtle">--</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $user->staff_rank->color() }}">{{ $user->staff_rank->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap">{{ $entry['todos_worked'] }} @if($entry['todos_worked_prev'] !== null)<span class="text-zinc-400 text-xs">({{ $entry['todos_worked_prev'] }})</span>@endif</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($entry['todos_open'] > 0)
                                <flux:badge size="sm" color="amber">{{ $entry['todos_open'] }}</flux:badge>
                            @else
                                0
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap">{{ $entry['tickets_worked'] }} @if($entry['tickets_worked_prev'] !== null)<span class="text-zinc-400 text-xs">({{ $entry['tickets_worked_prev'] }})</span>@endif</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($entry['tickets_open'] > 0)
                                <flux:badge size="sm" color="amber">{{ $entry['tickets_open'] }}</flux:badge>
                            @else
                                0
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $entry['reports_submitted'] }} / {{ $entry['total_meetings_3mo'] }}
                            @if($entry['reports_missed'] > 0)
                                @php
                                    $missedColor = match(true) {
                                        $entry['reports_missed'] >= 3 => 'red',
                                        $entry['reports_missed'] === 2 => 'amber',
                                        default => 'blue',
                                    };
                                @endphp
                                <flux:badge size="sm" color="{{ $missedColor }}">{{ $entry['reports_missed'] }} missed</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($entry['is_jrcrew'])
                                <flux:text variant="subtle">---</flux:text>
                            @else
                                {{ $entry['meetings_attended'] }} / {{ $entry['total_meetings_3mo'] }}
                                @if($entry['is_officer'] && $entry['meetings_missed'] !== null && $entry['meetings_missed'] > 0)
                                    <flux:badge size="sm" color="red">{{ $entry['meetings_missed'] }} missed</flux:badge>
                                @endif
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="ghost" wire:click="viewStaffDetail({{ $user->id }})">
                                Detail
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="mt-4">
        {{ $staffTable->links() }}
    </div>

    <flux:text variant="subtle" class="text-xs mt-2">
        Numbers in parentheses are from previous iteration. Worked = completed/closed in iteration. Reports and attendance over last 3 months.
    </flux:text>
</flux:card>

{{-- Staff Detail Modal --}}
<flux:modal name="staff-detail-modal" class="w-full md:w-2/3 lg:w-1/2">
    @if($this->staffDetail)
        @php $detail = $this->staffDetail; @endphp
        <div class="space-y-4">
            <flux:heading size="lg">{{ $detail['user']->name }} — 3 Month History</flux:heading>

            @if($detail['user']->staff_department)
                <flux:badge size="sm">{{ $detail['user']->staff_department->label() }}</flux:badge>
            @endif
            <flux:badge size="sm" color="{{ $detail['user']->staff_rank->color() }}">{{ $detail['user']->staff_rank->label() }}</flux:badge>

            @if(count($detail['iterations']) > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Iteration</flux:table.column>
                        <flux:table.column>Todos Worked</flux:table.column>
                        <flux:table.column>Tickets Worked</flux:table.column>
                        <flux:table.column>Report</flux:table.column>
                        <flux:table.column>Attended</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($detail['iterations'] as $iter)
                            <flux:table.row wire:key="iter-{{ $this->selectedStaffId }}-{{ $loop->index }}">
                                <flux:table.cell>{{ $iter['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $iter['completed'] }}</flux:table.cell>
                                <flux:table.cell>{{ $iter['tickets_worked'] }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($iter['report_submitted'] === true)
                                        <flux:badge size="sm" color="green">Yes</flux:badge>
                                    @elseif($iter['report_submitted'] === false)
                                        <flux:badge size="sm" color="red">No</flux:badge>
                                    @else
                                        <flux:text variant="subtle">--</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($iter['attended'] === true)
                                        <flux:badge size="sm" color="green">Yes</flux:badge>
                                    @elseif($iter['attended'] === false)
                                        <flux:badge size="sm" color="red">No</flux:badge>
                                    @else
                                        <flux:text variant="subtle">--</flux:text>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text variant="subtle">No historical iteration data available.</flux:text>
            @endif
        </div>
    @endif
</flux:modal>
</div>
