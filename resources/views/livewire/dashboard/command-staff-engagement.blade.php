<?php

use App\Actions\GetIterationBoundaries;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffRank;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Task;
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
            ->orderByRaw('CAST(staff_rank AS UNSIGNED) DESC')
            ->orderBy('name')
            ->paginate(15, pageName: 'staff-page');

        $staffIds = $staffUsers->pluck('id');

        // Batch-load current iteration task stats
        $currentTaskAssigned = Task::whereIn('assigned_to_user_id', $staffIds)
            ->whereBetween('created_at', [$cs, $ce])
            ->selectRaw('assigned_to_user_id, COUNT(*) as count')
            ->groupBy('assigned_to_user_id')
            ->pluck('count', 'assigned_to_user_id');

        $currentTaskCompleted = Task::whereIn('assigned_to_user_id', $staffIds)
            ->where('status', TaskStatus::Completed)
            ->whereBetween('completed_at', [$cs, $ce])
            ->selectRaw('assigned_to_user_id, COUNT(*) as count')
            ->groupBy('assigned_to_user_id')
            ->pluck('count', 'assigned_to_user_id');

        // Previous iteration task stats
        $previousTaskAssigned = collect();
        $previousTaskCompleted = collect();
        if ($hasPrevious) {
            $previousTaskAssigned = Task::whereIn('assigned_to_user_id', $staffIds)
                ->whereBetween('created_at', [$ps, $pe])
                ->selectRaw('assigned_to_user_id, COUNT(*) as count')
                ->groupBy('assigned_to_user_id')
                ->pluck('count', 'assigned_to_user_id');

            $previousTaskCompleted = Task::whereIn('assigned_to_user_id', $staffIds)
                ->where('status', TaskStatus::Completed)
                ->whereBetween('completed_at', [$ps, $pe])
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
            $currentTaskAssigned, $currentTaskCompleted,
            $previousTaskAssigned, $previousTaskCompleted,
            $reportsSubmitted, $totalMeetings3mo,
            $meetingsAttended, $hasPrevious
        ) {
            $reportsMissed = $totalMeetings3mo - $reportsSubmitted->get($user->id, 0);
            $meetingsMissed = $totalMeetings3mo - $meetingsAttended->get($user->id, 0);

            return [
                'user' => $user,
                'current_assigned' => $currentTaskAssigned->get($user->id, 0),
                'current_completed' => $currentTaskCompleted->get($user->id, 0),
                'previous_assigned' => $hasPrevious ? $previousTaskAssigned->get($user->id, 0) : null,
                'previous_completed' => $hasPrevious ? $previousTaskCompleted->get($user->id, 0) : null,
                'reports_submitted' => $reportsSubmitted->get($user->id, 0),
                'reports_missed' => $reportsMissed,
                'meetings_attended' => $meetingsAttended->get($user->id, 0),
                'meetings_missed' => $user->staff_rank->value >= StaffRank::CrewMember->value ? $meetingsMissed : null,
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

        $user = User::findOrFail($this->selectedStaffId);
        $boundaries = GetIterationBoundaries::run();
        $iterations = $boundaries['iterations_3mo'];

        $detail = [];
        foreach ($iterations as $iter) {
            $start = $iter['start'];
            $end = $iter['end'];
            $meeting = $iter['meeting'];

            $assigned = Task::where('assigned_to_user_id', $user->id)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $completed = Task::where('assigned_to_user_id', $user->id)
                ->where('status', TaskStatus::Completed)
                ->whereBetween('completed_at', [$start, $end])
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
                'assigned' => $assigned,
                'completed' => $completed,
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
                <flux:table.column>Todos (Current)</flux:table.column>
                <flux:table.column>Todos (Prev)</flux:table.column>
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
                            {{ $entry['current_assigned'] }} / {{ $entry['current_completed'] }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($entry['previous_assigned'] !== null)
                                {{ $entry['previous_assigned'] }} / {{ $entry['previous_completed'] }}
                            @else
                                <flux:text variant="subtle">--</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $entry['reports_submitted'] }} / {{ $entry['total_meetings_3mo'] }}
                            @if($entry['reports_missed'] > 0)
                                <flux:badge size="sm" color="red">{{ $entry['reports_missed'] }} missed</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $entry['meetings_attended'] }} / {{ $entry['total_meetings_3mo'] }}
                            @if($entry['meetings_missed'] !== null && $entry['meetings_missed'] > 0)
                                <flux:badge size="sm" color="red">{{ $entry['meetings_missed'] }} missed</flux:badge>
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
        Todos shown as assigned / completed. Reports and attendance shown as count / total meetings.
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
                        <flux:table.column>Assigned</flux:table.column>
                        <flux:table.column>Completed</flux:table.column>
                        <flux:table.column>Report</flux:table.column>
                        <flux:table.column>Attended</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($detail['iterations'] as $iter)
                            <flux:table.row>
                                <flux:table.cell>{{ $iter['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $iter['assigned'] }}</flux:table.cell>
                                <flux:table.cell>{{ $iter['completed'] }}</flux:table.cell>
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
