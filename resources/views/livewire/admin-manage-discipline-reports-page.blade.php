<?php

use App\Actions\PublishDisciplineReport;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    protected const ALLOWED_SORTS = ['created_at', 'published_at', 'severity', 'status'];

    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    public int $perPage = 15;
    public string $filterStatus = '';
    public string $filterSeverity = '';

    public ?int $viewingReportId = null;

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSeverity(): void
    {
        $this->resetPage();
    }

    public function getReportsProperty()
    {
        $this->authorize('view-discipline-report-log');

        $query = DisciplineReport::with(['subject', 'reporter', 'publisher']);

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterSeverity) {
            $query->where('severity', $this->filterSeverity);
        }

        $sortColumn = in_array($this->sortBy, self::ALLOWED_SORTS) ? $this->sortBy : 'created_at';
        $query->orderBy($sortColumn, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function viewReport(int $reportId): void
    {
        $this->viewingReportId = $reportId;
        Flux::modal('acp-view-report-modal')->show();
    }

    public function publishReport(int $reportId): void
    {
        $report = DisciplineReport::findOrFail($reportId);
        $this->authorize('publish', $report);

        PublishDisciplineReport::run($report, Auth::user());

        Flux::toast('Report published.', 'Published', variant: 'success');
    }
}; ?>

<div>
    <div class="flex gap-4 mb-4">
        <flux:select wire:model.live="filterStatus" placeholder="All Statuses" class="w-40">
            <flux:select.option value="">All Statuses</flux:select.option>
            @foreach(ReportStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterSeverity" placeholder="All Severities" class="w-40">
            <flux:select.option value="">All Severities</flux:select.option>
            @foreach(ReportSeverity::cases() as $severity)
                <flux:select.option value="{{ $severity->value }}">{{ $severity->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Subject</flux:table.column>
            <flux:table.column>Reporter</flux:table.column>
            <flux:table.column>Location</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'severity'" :direction="$sortDirection" wire:click="sort('severity')">Severity</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'published_at'" :direction="$sortDirection" wire:click="sort('published_at')">Published</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->reports as $report)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:link href="{{ route('profile.show', $report->subject) }}">{{ $report->subject->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $report->reporter->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $report->location->color() }}" size="sm">{{ $report->location->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $report->severity->color() }}" size="sm">{{ $report->severity->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $report->status->color() }}" size="sm">{{ $report->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $report->created_at->format('M j, Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $report->published_at?->format('M j, Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1 justify-end">
                            <flux:button size="xs" variant="ghost" wire:click="viewReport({{ $report->id }})">View</flux:button>
                            @if($report->isDraft())
                                @can('publish', $report)
                                    <flux:button size="xs" variant="primary" wire:click="publishReport({{ $report->id }})"
                                        wire:confirm="Publish this report? The user and their parents will be notified.">
                                        Publish
                                    </flux:button>
                                @endcan
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->reports->links() }}
    </div>

    {{-- View Report Modal --}}
    <flux:modal name="acp-view-report-modal" class="w-full md:w-1/2 xl:w-1/3">
        @if($viewingReportId)
            @php $viewReport = DisciplineReport::with(['subject', 'reporter', 'publisher'])->find($viewingReportId); @endphp
            @if($viewReport)
                <div class="space-y-4">
                    <flux:heading size="lg">Discipline Report #{{ $viewReport->id }}</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text class="font-medium text-sm">Subject</flux:text>
                            <flux:link href="{{ route('profile.show', $viewReport->subject) }}">{{ $viewReport->subject->name }}</flux:link>
                        </div>
                        <div>
                            <flux:text class="font-medium text-sm">Reporter</flux:text>
                            <flux:text>{{ $viewReport->reporter->name }}</flux:text>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text class="font-medium text-sm">Location</flux:text>
                            <flux:badge color="{{ $viewReport->location->color() }}">{{ $viewReport->location->label() }}</flux:badge>
                        </div>
                        <div>
                            <flux:text class="font-medium text-sm">Severity</flux:text>
                            <flux:badge color="{{ $viewReport->severity->color() }}">{{ $viewReport->severity->label() }} ({{ $viewReport->severity->points() }}pt)</flux:badge>
                        </div>
                    </div>

                    <div>
                        <flux:text class="font-medium text-sm">What Happened</flux:text>
                        <flux:text>{{ $viewReport->description }}</flux:text>
                    </div>

                    @if($viewReport->witnesses)
                        <div>
                            <flux:text class="font-medium text-sm">Witnesses</flux:text>
                            <flux:text>{{ $viewReport->witnesses }}</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text class="font-medium text-sm">Actions Taken</flux:text>
                        <flux:text>{{ $viewReport->actions_taken }}</flux:text>
                    </div>

                    <flux:separator variant="subtle" />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text class="font-medium text-sm">Status</flux:text>
                            <flux:badge color="{{ $viewReport->status->color() }}">{{ $viewReport->status->label() }}</flux:badge>
                        </div>
                        <div>
                            <flux:text class="font-medium text-sm">Created</flux:text>
                            <flux:text>{{ $viewReport->created_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    </div>

                    @if($viewReport->publisher)
                        <div>
                            <flux:text class="font-medium text-sm">Published By</flux:text>
                            <flux:text>{{ $viewReport->publisher->name }} on {{ $viewReport->published_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </flux:modal>
</div>
