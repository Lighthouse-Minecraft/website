<?php

use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public function getRecentReportsProperty()
    {
        return DisciplineReport::with(['subject', 'reporter', 'category'])
            ->latest()
            ->limit(5)
            ->get();
    }

    public function getPendingCountProperty(): int
    {
        return DisciplineReport::draft()->count();
    }

    public function getTopRiskUsersProperty()
    {
        // Get users with published reports in last 90 days, calculate risk in PHP
        $reportsByUser = DisciplineReport::published()
            ->where('published_at', '>=', now()->subDays(90))
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
                if ($report->published_at >= now()->subDays(30)) {
                    $score30 += $points;
                }
                if ($report->published_at >= now()->subDays(7)) {
                    $score7 += $points;
                }
            }

            $total = $score7 + $score30 + $score90;
            if ($total > 0) {
                $riskUsers[] = [
                    'user' => $reports->first()->subject,
                    'total' => $total,
                ];
            }
        }

        // Sort by total descending, take top 5
        usort($riskUsers, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice($riskUsers, 0, 5);
    }
}; ?>

<flux:card>
    <div class="flex items-center gap-3">
        <flux:heading size="md">Discipline Reports</flux:heading>
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
                <div class="flex items-center gap-2 text-sm">
                    <flux:link href="{{ route('profile.show', $report->subject) }}">
                        {{ $report->subject->name }}
                    </flux:link>
                    @if($report->category)
                        <flux:badge color="{{ $report->category->color }}" size="sm">{{ $report->category->name }}</flux:badge>
                    @endif
                    <flux:badge color="{{ $report->severity->color() }}" size="sm">{{ $report->severity->label() }}</flux:badge>
                    <flux:badge color="{{ $report->status->color() }}" size="sm">{{ $report->status->label() }}</flux:badge>
                    <flux:spacer />
                    <flux:text variant="subtle" class="text-xs">{{ $report->created_at->format('M j') }}</flux:text>
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
                <div class="flex items-center gap-2 text-sm">
                    <flux:link href="{{ route('profile.show', $entry['user']) }}">
                        {{ $entry['user']->name }}
                    </flux:link>
                    <flux:spacer />
                    <flux:badge color="{{ \App\Models\User::riskScoreColor($entry['total']) }}" size="sm">
                        {{ $entry['total'] }}
                    </flux:badge>
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
