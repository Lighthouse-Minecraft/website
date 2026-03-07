<?php

use App\Actions\PublishDisciplineReport;
use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public ?int $viewingReportId = null;

    public function getRecentReportsProperty()
    {
        return DisciplineReport::with(['subject', 'reporter', 'category'])
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()
            ->limit(50)
            ->get();
    }

    public function getPendingCountProperty(): int
    {
        return DisciplineReport::draft()->count();
    }

    public function getTopRiskUsersProperty()
    {
        return Cache::remember('dashboard.top_risk_users', 300, function () {
            $now = now();
            $cutoff7 = $now->copy()->subDays(7);
            $cutoff30 = $now->copy()->subDays(30);
            $cutoff90 = $now->copy()->subDays(90);

            $reportsByUser = DisciplineReport::published()
                ->where('published_at', '>=', $cutoff90)
                ->with('subject')
                ->get()
                ->groupBy('subject_user_id');

            $riskUsers = [];
            foreach ($reportsByUser as $userId => $reports) {
                $score7 = 0;
                $score30 = 0;
                $score90 = 0;

                foreach ($reports as $report) {
                    $points = $report->severity->points();
                    $score90 += $points;
                    if ($report->published_at >= $cutoff30) {
                        $score30 += $points;
                    }
                    if ($report->published_at >= $cutoff7) {
                        $score7 += $points;
                    }
                }

                $total = $score7 + $score30 + $score90;
                if ($total > 0) {
                    $riskUsers[] = [
                        'user' => $reports->first()->subject,
                        'total' => $total,
                        '7d' => $score7,
                        '30d' => $score30,
                        '90d' => $score90,
                    ];
                }
            }

            usort($riskUsers, fn ($a, $b) => $b['total'] <=> $a['total']);

            return array_slice($riskUsers, 0, 5);
        });
    }

    public function getViewingReportProperty()
    {
        if (! $this->viewingReportId) {
            return null;
        }

        return DisciplineReport::with(['subject', 'reporter', 'publisher', 'category'])
            ->find($this->viewingReportId);
    }

    public function viewReport(int $reportId): void
    {
        $report = DisciplineReport::findOrFail($reportId);
        $this->authorize('view', $report);

        $this->viewingReportId = $reportId;
        Flux::modal('widget-view-report-modal')->show();
    }

    public function publishReport(int $reportId): void
    {
        $report = DisciplineReport::findOrFail($reportId);
        $this->authorize('publish', $report);

        PublishDisciplineReport::run($report, Auth::user());

        $this->viewingReportId = null;
        Flux::modal('widget-view-report-modal')->close();
        Flux::toast('Staff report published.', 'Report Published', variant: 'success');
    }
}; ?>

<div>
<flux:card>
    <div class="flex items-center gap-3">
        <flux:heading size="md">Staff User Reports</flux:heading>
        <flux:spacer />
        @if($this->pendingCount > 0)
            <flux:badge color="amber" size="sm">{{ $this->pendingCount }} pending</flux:badge>
        @endif
    </div>
    <flux:separator variant="subtle" class="my-2" />

    {{-- Recent Reports --}}
    @if($this->recentReports->isEmpty())
        <flux:text variant="subtle" class="py-2">No reports yet.</flux:text>
    @else
        <div class="space-y-2">
            @foreach($this->recentReports as $report)
                <div wire:key="report-{{ $report->id }}" class="flex items-center gap-2 text-sm">
                    <flux:avatar size="xs" :src="$report->subject->avatarUrl()" :initials="$report->subject->initials()" />
                    <flux:link href="{{ route('profile.show', $report->subject) }}">
                        {{ $report->subject->name }}
                    </flux:link>
                    @if($report->category)
                        <flux:badge color="{{ $report->category->color }}" size="sm">{{ $report->category->name }}</flux:badge>
                    @endif
                    <flux:badge color="{{ $report->severity->color() }}" size="sm">{{ $report->severity->label() }}</flux:badge>
                    @if($report->isDraft())
                        <flux:badge color="amber" size="sm">Draft</flux:badge>
                    @endif
                    <flux:spacer />
                    <flux:text variant="subtle" class="text-xs">{{ $report->created_at->format('M j') }}</flux:text>
                    <flux:button size="xs" variant="ghost" wire:click="viewReport({{ $report->id }})">
                        View
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Top Risk Users --}}
    @if(count($this->topRiskUsers) > 0)
        <flux:separator variant="subtle" class="my-3" />
        <flux:text class="font-medium text-sm mb-2">Highest Risk</flux:text>
        <div class="space-y-1">
            @foreach($this->topRiskUsers as $entry)
                <div wire:key="risk-{{ $entry['user']->id }}" class="flex items-center gap-2 text-sm">
                    <flux:avatar size="xs" :src="$entry['user']->avatarUrl()" :initials="$entry['user']->initials()" />
                    <flux:link href="{{ route('profile.show', $entry['user']) }}">
                        {{ $entry['user']->name }}
                    </flux:link>
                    <flux:spacer />
                    <flux:tooltip content="7d: {{ $entry['7d'] }} | 30d: {{ $entry['30d'] }} | 90d: {{ $entry['90d'] }}">
                        <flux:badge color="{{ \App\Models\User::riskScoreColor($entry['total']) }}" size="sm">
                            {{ $entry['total'] }}
                        </flux:badge>
                    </flux:tooltip>
                </div>
            @endforeach
        </div>
    @endif

    <flux:separator variant="subtle" class="my-3" />
    <div class="flex justify-end">
        <flux:link href="{{ route('acp.index', ['category' => 'logs', 'tab' => 'discipline-report-log']) }}" class="text-sm">
            View All Reports
        </flux:link>
    </div>
</flux:card>

{{-- View Report Modal --}}
<flux:modal name="widget-view-report-modal" class="w-full md:w-1/2 xl:w-1/3">
    @if($this->viewingReport)
        @php $viewReport = $this->viewingReport; @endphp
        <div class="space-y-4">
                <flux:heading size="lg">Staff User Report</flux:heading>

                <div class="flex items-center gap-3">
                    <flux:avatar size="sm" :src="$viewReport->subject->avatarUrl()" :initials="$viewReport->subject->initials()" />
                    <div>
                        <flux:text class="font-bold text-sm">Subject</flux:text>
                        <flux:link href="{{ route('profile.show', $viewReport->subject) }}">{{ $viewReport->subject->name }}</flux:link>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if($viewReport->category)
                        <flux:badge color="{{ $viewReport->category->color }}">{{ $viewReport->category->name }}</flux:badge>
                    @endif
                    <flux:badge color="{{ $viewReport->location->color() }}">{{ $viewReport->location->label() }}</flux:badge>
                    <flux:badge color="{{ $viewReport->severity->color() }}">{{ $viewReport->severity->label() }}</flux:badge>
                    <flux:badge color="{{ $viewReport->status->color() }}">{{ $viewReport->status->label() }}</flux:badge>
                </div>

                <div>
                    <flux:text class="font-bold text-sm">What Happened</flux:text>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! Str::markdown($viewReport->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>

                @if($viewReport->witnesses)
                    <div>
                        <flux:text class="font-bold text-sm">Witnesses</flux:text>
                        <flux:text>{{ $viewReport->witnesses }}</flux:text>
                    </div>
                @endif

                <div>
                    <flux:text class="font-bold text-sm">Actions Taken</flux:text>
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! Str::markdown($viewReport->actions_taken, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                </div>

                <flux:separator variant="subtle" />
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text class="font-bold text-sm">Reporter</flux:text>
                        <div class="flex items-center gap-2 mt-1">
                            <flux:avatar size="xs" :src="$viewReport->reporter->avatarUrl()" :initials="$viewReport->reporter->initials()" />
                            <flux:link href="{{ route('profile.show', $viewReport->reporter) }}">{{ $viewReport->reporter->name }}</flux:link>
                        </div>
                    </div>
                    @if($viewReport->publisher)
                        <div>
                            <flux:text class="font-bold text-sm">Published By</flux:text>
                            <div class="flex items-center gap-2 mt-1">
                                <flux:avatar size="xs" :src="$viewReport->publisher->avatarUrl()" :initials="$viewReport->publisher->initials()" />
                                <flux:link href="{{ route('profile.show', $viewReport->publisher) }}">{{ $viewReport->publisher->name }}</flux:link>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text class="font-bold text-sm">Created</flux:text>
                        <flux:text>{{ $viewReport->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    @if($viewReport->published_at)
                        <div>
                            <flux:text class="font-bold text-sm">Published</flux:text>
                            <flux:text>{{ $viewReport->published_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                </div>

                <flux:separator variant="subtle" />
                <div class="flex gap-2 justify-end">
                    <flux:link href="{{ route('profile.show', $viewReport->subject) }}" variant="ghost">
                        <flux:button variant="ghost" icon="pencil-square">Go to Profile to Edit</flux:button>
                    </flux:link>
                    @if($viewReport->isDraft())
                        @can('publish', $viewReport)
                            <flux:button variant="primary" icon="check-circle"
                                wire:click="publishReport({{ $viewReport->id }})"
                                wire:confirm="Are you sure you want to publish this report? This will notify the user and cannot be undone.">
                                Publish
                            </flux:button>
                        @elsecan('update', $viewReport)
                            <flux:text variant="subtle" class="text-xs italic">Another officer must publish this report.</flux:text>
                        @endcan
                    @endif
                </div>
        </div>
    @endif
</flux:modal>
</div>
