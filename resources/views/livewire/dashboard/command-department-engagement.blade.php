<?php

use App\Actions\GetIterationBoundaries;
use App\Enums\ReportStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\TaskStatus;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\DisciplineReport;
use App\Models\MeetingReport;
use App\Models\Task;
use App\Models\Thread;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public string $activeDetailMetric = '';

    public function getMetricsProperty(): array
    {
        $boundaries = GetIterationBoundaries::run();
        $cs = $boundaries['current_start'];
        $ce = $boundaries['current_end'];
        $ps = $boundaries['previous_start'];
        $pe = $boundaries['previous_end'];
        $hasPrevious = $boundaries['has_previous'];
        $previousMeeting = $boundaries['previous_meeting'];

        $departments = StaffDepartment::cases();

        $currentMetrics = Cache::flexible('command_dashboard.department.current', [3600, 43200], function () use ($cs, $ce, $departments) {
            return $this->computeDeptMetrics($cs, $ce, $departments);
        });

        $previousMetrics = null;
        if ($hasPrevious) {
            $cacheKey = 'command_dashboard.department.previous.' . $ps->timestamp . '.' . $pe->timestamp;
            $previousMetrics = Cache::remember($cacheKey, now()->addHours(24), function () use ($ps, $pe, $departments) {
                return $this->computeDeptMetrics($ps, $pe, $departments);
            });
        }

        // Discipline reports
        $disciplineCurrent = Cache::flexible('command_dashboard.discipline.current', [3600, 43200], function () use ($cs, $ce) {
            return [
                'published' => DisciplineReport::published()->whereBetween('published_at', [$cs, $ce])->count(),
                'drafts' => DisciplineReport::draft()->count(),
            ];
        });

        $disciplinePrevious = null;
        if ($hasPrevious) {
            $cacheKey = 'command_dashboard.discipline.previous.' . $ps->timestamp . '.' . $pe->timestamp;
            $disciplinePrevious = Cache::remember($cacheKey, now()->addHours(24), function () use ($ps, $pe) {
                return [
                    'published' => DisciplineReport::published()->whereBetween('published_at', [$ps, $pe])->count(),
                ];
            });
        }

        // Staff report completion & attendance (previous iteration only)
        $reportCompletion = null;
        $attendanceRate = null;
        if ($previousMeeting) {
            $cacheKey = 'command_dashboard.meeting_stats.' . $previousMeeting->id;
            $meetingStats = Cache::remember($cacheKey, now()->addHours(24), function () use ($previousMeeting) {
                $totalStaff = User::where('staff_rank', '!=', StaffRank::None)
                    ->count();
                $submittedReports = MeetingReport::where('meeting_id', $previousMeeting->id)
                    ->whereNotNull('submitted_at')
                    ->count();

                $nonJrCrewCount = User::where('staff_rank', '>=', StaffRank::CrewMember->value)
                    ->count();
                $attendeeIds = $previousMeeting->attendees()->pluck('users.id');
                $attendedNonJrCrew = User::whereIn('id', $attendeeIds)
                    ->where('staff_rank', '>=', StaffRank::CrewMember->value)
                    ->count();

                return [
                    'report_completion' => $totalStaff > 0 ? round(($submittedReports / $totalStaff) * 100) : 0,
                    'submitted_reports' => $submittedReports,
                    'total_staff' => $totalStaff,
                    'attendance_rate' => $nonJrCrewCount > 0 ? round(($attendedNonJrCrew / $nonJrCrewCount) * 100) : 0,
                    'attended' => $attendedNonJrCrew,
                    'expected_attendance' => $nonJrCrewCount,
                ];
            });

            $reportCompletion = $meetingStats['report_completion'];
            $attendanceRate = $meetingStats['attendance_rate'];
        }

        return [
            'departments' => $departments,
            'current' => $currentMetrics,
            'previous' => $previousMetrics,
            'has_previous' => $hasPrevious,
            'discipline_current' => $disciplineCurrent,
            'discipline_previous' => $disciplinePrevious,
            'report_completion' => $reportCompletion,
            'attendance_rate' => $attendanceRate,
            'meeting_stats' => $meetingStats ?? null,
        ];
    }

    public function getTimelineDataProperty(): array
    {
        if (! $this->activeDetailMetric) {
            return [];
        }

        $boundaries = GetIterationBoundaries::run();
        $iterations = $boundaries['iterations_3mo'];
        $data = [];

        foreach ($iterations as $iter) {
            $start = $iter['start'];
            $end = $iter['end'];
            $meeting = $iter['meeting'];

            $entry = ['label' => $start->format('M j') . ' - ' . $end->format('M j')];

            if ($this->activeDetailMetric === 'discipline') {
                $entry['published'] = DisciplineReport::published()->whereBetween('published_at', [$start, $end])->count();
            } elseif ($this->activeDetailMetric === 'reports' && $meeting) {
                $totalStaff = User::where('staff_rank', '!=', StaffRank::None)->count();
                $submitted = MeetingReport::where('meeting_id', $meeting->id)->whereNotNull('submitted_at')->count();
                $entry['submitted'] = $submitted;
                $entry['total'] = $totalStaff;
                $entry['pct'] = $totalStaff > 0 ? round(($submitted / $totalStaff) * 100) : 0;
            } elseif ($this->activeDetailMetric === 'attendance' && $meeting) {
                $nonJrCrewCount = User::where('staff_rank', '>=', StaffRank::CrewMember->value)->count();
                $attendeeIds = $meeting->attendees()->pluck('users.id');
                $attended = User::whereIn('id', $attendeeIds)->where('staff_rank', '>=', StaffRank::CrewMember->value)->count();
                $entry['attended'] = $attended;
                $entry['total'] = $nonJrCrewCount;
                $entry['pct'] = $nonJrCrewCount > 0 ? round(($attended / $nonJrCrewCount) * 100) : 0;
            }

            $data[] = $entry;
        }

        return $data;
    }

    public function showDetail(string $metric): void
    {
        $this->authorize('view-command-dashboard');
        $this->activeDetailMetric = $metric;
        Flux::modal('department-detail-modal')->show();
    }

    private function computeDeptMetrics($start, $end, array $departments): array
    {
        $metrics = [];
        foreach ($departments as $dept) {
            $deptValue = $dept->value;

            $ticketsOpened = Thread::where('type', ThreadType::Ticket)
                ->where('department', $deptValue)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $ticketsClosed = Thread::where('type', ThreadType::Ticket)
                ->where('department', $deptValue)
                ->where('status', ThreadStatus::Closed)
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $ticketsRemaining = Thread::where('type', ThreadType::Ticket)
                ->where('department', $deptValue)
                ->whereNotIn('status', [ThreadStatus::Closed])
                ->count();

            $todosCreated = Task::where('section_key', $deptValue)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $todosCompleted = Task::where('section_key', $deptValue)
                ->where('status', TaskStatus::Completed)
                ->whereBetween('completed_at', [$start, $end])
                ->count();

            $todosRemaining = Task::where('section_key', $deptValue)
                ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Archived])
                ->count();

            $metrics[$deptValue] = [
                'tickets_opened' => $ticketsOpened,
                'tickets_closed' => $ticketsClosed,
                'tickets_remaining' => $ticketsRemaining,
                'todos_created' => $todosCreated,
                'todos_completed' => $todosCompleted,
                'todos_remaining' => $todosRemaining,
            ];
        }

        return $metrics;
    }
}; ?>

<div>
<flux:card class="w-full">
    <flux:heading size="md" class="mb-4">Department Engagement</flux:heading>
    <flux:separator variant="subtle" class="mb-4" />

    @php $metrics = $this->metrics; @endphp

    {{-- Tickets & Todos Per Department --}}
    <flux:text class="font-medium text-sm mb-2">Tickets / Todos by Department</flux:text>
    <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Department</flux:table.column>
                <flux:table.column>Tickets Opened</flux:table.column>
                <flux:table.column>Tickets Closed</flux:table.column>
                <flux:table.column>Open</flux:table.column>
                <flux:table.column>Todos Created</flux:table.column>
                <flux:table.column>Todos Done</flux:table.column>
                <flux:table.column>Remaining</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($metrics['departments'] as $dept)
                    @php
                        $dv = $dept->value;
                        $cur = $metrics['current'][$dv] ?? null;
                        $prev = $metrics['previous'][$dv] ?? null;
                    @endphp
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $dept->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap">{{ $cur['tickets_opened'] ?? 0 }} @if($prev)<span class="text-zinc-400 text-xs">({{ $prev['tickets_opened'] ?? 0 }})</span>@endif</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap">{{ $cur['tickets_closed'] ?? 0 }} @if($prev)<span class="text-zinc-400 text-xs">({{ $prev['tickets_closed'] ?? 0 }})</span>@endif</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if(($cur['tickets_remaining'] ?? 0) > 0)
                                <flux:badge size="sm" color="amber">{{ $cur['tickets_remaining'] }}</flux:badge>
                            @else
                                0
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap">{{ $cur['todos_created'] ?? 0 }} @if($prev)<span class="text-zinc-400 text-xs">({{ $prev['todos_created'] ?? 0 }})</span>@endif</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="whitespace-nowrap">{{ $cur['todos_completed'] ?? 0 }} @if($prev)<span class="text-zinc-400 text-xs">({{ $prev['todos_completed'] ?? 0 }})</span>@endif</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if(($cur['todos_remaining'] ?? 0) > 0)
                                <flux:badge size="sm" color="amber">{{ $cur['todos_remaining'] }}</flux:badge>
                            @else
                                0
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
    <flux:text variant="subtle" class="text-xs mt-1">Numbers in parentheses are from previous iteration. Tickets closed based on last status change date.</flux:text>

    <flux:separator variant="subtle" class="my-4" />

    {{-- Discipline Reports --}}
    <div class="grid grid-cols-2 gap-3">
        <button wire:click="showDetail('discipline')" class="text-left p-3 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition">
            <flux:text variant="subtle" class="text-xs">Discipline Reports Published</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold text-white">{{ $metrics['discipline_current']['published'] }}</span>
                @if($metrics['discipline_previous'])
                    @php $delta = $metrics['discipline_current']['published'] - $metrics['discipline_previous']['published']; @endphp
                    <flux:badge size="sm" color="{{ $delta <= 0 ? 'green' : 'amber' }}">
                        {{ $delta >= 0 ? '+' : '' }}{{ $delta }}
                    </flux:badge>
                @endif
            </div>
        </button>

        <div class="p-3 rounded-lg bg-zinc-800">
            <flux:text variant="subtle" class="text-xs">Reports in Draft</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold text-white">{{ $metrics['discipline_current']['drafts'] }}</span>
                @if($metrics['discipline_current']['drafts'] > 0)
                    <flux:badge size="sm" color="amber">pending review</flux:badge>
                @endif
            </div>
        </div>
    </div>

    <flux:separator variant="subtle" class="my-4" />

    {{-- Staff Reports & Attendance --}}
    <flux:text class="font-medium text-sm mb-2">Last Meeting</flux:text>
    <div class="grid grid-cols-2 gap-3">
        <button wire:click="showDetail('reports')" class="text-left p-3 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition">
            <flux:text variant="subtle" class="text-xs">Staff Report Completion</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                @if($metrics['report_completion'] !== null)
                    <span class="text-2xl font-bold text-white">{{ $metrics['report_completion'] }}%</span>
                    @if($metrics['meeting_stats'])
                        <flux:text variant="subtle" class="text-xs">{{ $metrics['meeting_stats']['submitted_reports'] }}/{{ $metrics['meeting_stats']['total_staff'] }}</flux:text>
                    @endif
                @else
                    <span class="text-2xl font-bold text-zinc-500">--</span>
                    <flux:text variant="subtle" class="text-xs">No completed meeting</flux:text>
                @endif
            </div>
        </button>

        <button wire:click="showDetail('attendance')" class="text-left p-3 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition">
            <flux:text variant="subtle" class="text-xs">Meeting Attendance (excl. Jr Crew)</flux:text>
            <div class="flex items-baseline gap-2 mt-1">
                @if($metrics['attendance_rate'] !== null)
                    <span class="text-2xl font-bold text-white">{{ $metrics['attendance_rate'] }}%</span>
                    @if($metrics['meeting_stats'])
                        <flux:text variant="subtle" class="text-xs">{{ $metrics['meeting_stats']['attended'] }}/{{ $metrics['meeting_stats']['expected_attendance'] }}</flux:text>
                    @endif
                @else
                    <span class="text-2xl font-bold text-zinc-500">--</span>
                    <flux:text variant="subtle" class="text-xs">No completed meeting</flux:text>
                @endif
            </div>
        </button>
    </div>
</flux:card>

{{-- Detail Modal --}}
<flux:modal name="department-detail-modal" class="w-full md:w-2/3 lg:w-1/2">
    <div class="space-y-4">
        <flux:heading size="lg">
            {{ match($this->activeDetailMetric) {
                'discipline' => 'Discipline Reports',
                'reports' => 'Staff Report Completion',
                'attendance' => 'Meeting Attendance',
                default => 'Details',
            } }} — 3 Month Timeline
        </flux:heading>

        @if(count($this->timelineData) > 0)
            <div wire:key="dept-chart-{{ $this->activeDetailMetric }}">
                @if($this->activeDetailMetric === 'discipline')
                    <flux:chart :value="$this->timelineData" class="aspect-[5/2]">
                        <flux:chart.svg gutter="8 8 28 8">
                            <flux:chart.axis axis="y" field="published" tick-start="0">
                                <flux:chart.axis.grid class="text-zinc-700" />
                                <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                            </flux:chart.axis>
                            <flux:chart.axis axis="x" field="label">
                                <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                                <flux:chart.axis.line class="text-zinc-600" />
                            </flux:chart.axis>
                            <flux:chart.area field="published" class="text-amber-500/10" />
                            <flux:chart.line field="published" class="text-amber-500" />
                            <flux:chart.point field="published" class="text-amber-400" />
                            <flux:chart.cursor />
                        </flux:chart.svg>
                        <flux:chart.tooltip>
                            <flux:chart.tooltip.heading field="label" />
                            <flux:chart.tooltip.value field="published" label="Published" />
                        </flux:chart.tooltip>
                    </flux:chart>
                @elseif($this->activeDetailMetric === 'reports')
                    <flux:chart :value="$this->timelineData" class="aspect-[5/2]">
                        <flux:chart.svg gutter="8 8 28 8">
                            <flux:chart.axis axis="y" field="pct" tick-start="0" tick-end="100" tick-suffix="%">
                                <flux:chart.axis.grid class="text-zinc-700" />
                                <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                            </flux:chart.axis>
                            <flux:chart.axis axis="x" field="label">
                                <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                                <flux:chart.axis.line class="text-zinc-600" />
                            </flux:chart.axis>
                            <flux:chart.area field="pct" class="text-blue-500/10" />
                            <flux:chart.line field="pct" class="text-blue-500" />
                            <flux:chart.point field="pct" class="text-blue-400" />
                            <flux:chart.cursor />
                        </flux:chart.svg>
                        <flux:chart.tooltip>
                            <flux:chart.tooltip.heading field="label" />
                            <flux:chart.tooltip.value field="pct" label="Completion %" />
                            <flux:chart.tooltip.value field="submitted" label="Submitted" />
                            <flux:chart.tooltip.value field="total" label="Total Staff" />
                        </flux:chart.tooltip>
                    </flux:chart>
                @elseif($this->activeDetailMetric === 'attendance')
                    <flux:chart :value="$this->timelineData" class="aspect-[5/2]">
                        <flux:chart.svg gutter="8 8 28 8">
                            <flux:chart.axis axis="y" field="pct" tick-start="0" tick-end="100" tick-suffix="%">
                                <flux:chart.axis.grid class="text-zinc-700" />
                                <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                            </flux:chart.axis>
                            <flux:chart.axis axis="x" field="label">
                                <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                                <flux:chart.axis.line class="text-zinc-600" />
                            </flux:chart.axis>
                            <flux:chart.area field="pct" class="text-green-500/10" />
                            <flux:chart.line field="pct" class="text-green-500" />
                            <flux:chart.point field="pct" class="text-green-400" />
                            <flux:chart.cursor />
                        </flux:chart.svg>
                        <flux:chart.tooltip>
                            <flux:chart.tooltip.heading field="label" />
                            <flux:chart.tooltip.value field="pct" label="Attendance %" />
                            <flux:chart.tooltip.value field="attended" label="Attended" />
                            <flux:chart.tooltip.value field="total" label="Expected" />
                        </flux:chart.tooltip>
                    </flux:chart>
                @endif
            </div>
        @else
            <flux:text variant="subtle">No historical iteration data available.</flux:text>
        @endif
    </div>
</flux:modal>
</div>
