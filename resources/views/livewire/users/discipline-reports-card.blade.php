<?php

use App\Actions\CreateDisciplineReport;
use App\Actions\PublishDisciplineReport;
use App\Actions\UpdateDisciplineReport;
use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public int $userId;

    #[Locked]
    public bool $isStaffViewing = false;

    // Create/Edit form fields
    public string $formDescription = '';
    public string $formLocation = '';
    public string $formWitnesses = '';
    public string $formActionsTaken = '';
    public string $formSeverity = '';

    // Editing state
    public ?int $editingReportId = null;

    // Viewing state
    public ?int $viewingReportId = null;

    public function mount(User $user): void
    {
        $authUser = Auth::user();
        $isStaff = $authUser->isAtLeastRank(StaffRank::JrCrew) || $authUser->hasRole('Admin');
        $isSelf = $authUser->id === $user->id;
        $isParent = $authUser->children()->where('child_user_id', $user->id)->exists();

        if (! $isStaff && ! $isSelf && ! $isParent) {
            abort(403);
        }

        $this->userId = $user->id;
        $this->isStaffViewing = $isStaff;
    }

    public function getUser(): User
    {
        return User::findOrFail($this->userId);
    }

    public function getReports()
    {
        $query = DisciplineReport::where('subject_user_id', $this->userId)
            ->latest();

        if (! $this->isStaffViewing) {
            $query->published();
        }

        return $query->get();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', DisciplineReport::class);

        $this->resetForm();
        Flux::modal('create-report-modal')->show();
    }

    public function createReport(): void
    {
        $this->authorize('create', DisciplineReport::class);

        $this->validate([
            'formDescription' => 'required|string|min:10',
            'formLocation' => 'required|string',
            'formActionsTaken' => 'required|string|min:5',
            'formSeverity' => 'required|string',
        ]);

        $subject = $this->getUser();

        CreateDisciplineReport::run(
            $subject,
            Auth::user(),
            $this->formDescription,
            ReportLocation::from($this->formLocation),
            $this->formActionsTaken,
            ReportSeverity::from($this->formSeverity),
            $this->formWitnesses ?: null,
        );

        $this->resetForm();
        Flux::modal('create-report-modal')->close();
        Flux::toast('Discipline report created.', 'Report Created', variant: 'success');
    }

    public function openEditModal(int $reportId): void
    {
        $report = DisciplineReport::findOrFail($reportId);
        $this->authorize('update', $report);

        $this->editingReportId = $reportId;
        $this->formDescription = $report->description;
        $this->formLocation = $report->location->value;
        $this->formWitnesses = $report->witnesses ?? '';
        $this->formActionsTaken = $report->actions_taken;
        $this->formSeverity = $report->severity->value;

        Flux::modal('edit-report-modal')->show();
    }

    public function updateReport(): void
    {
        $report = DisciplineReport::findOrFail($this->editingReportId);
        $this->authorize('update', $report);

        $this->validate([
            'formDescription' => 'required|string|min:10',
            'formLocation' => 'required|string',
            'formActionsTaken' => 'required|string|min:5',
            'formSeverity' => 'required|string',
        ]);

        UpdateDisciplineReport::run(
            $report,
            Auth::user(),
            $this->formDescription,
            ReportLocation::from($this->formLocation),
            $this->formActionsTaken,
            ReportSeverity::from($this->formSeverity),
            $this->formWitnesses ?: null,
        );

        $this->resetForm();
        $this->editingReportId = null;
        Flux::modal('edit-report-modal')->close();
        Flux::toast('Discipline report updated.', 'Report Updated', variant: 'success');
    }

    public function publishReport(int $reportId): void
    {
        $report = DisciplineReport::findOrFail($reportId);
        $this->authorize('publish', $report);

        PublishDisciplineReport::run($report, Auth::user());

        Flux::toast('Discipline report published.', 'Report Published', variant: 'success');
    }

    public function viewReport(int $reportId): void
    {
        $report = DisciplineReport::findOrFail($reportId);
        $this->authorize('view', $report);

        $this->viewingReportId = $reportId;
        Flux::modal('view-report-modal')->show();
    }

    private function resetForm(): void
    {
        $this->formDescription = '';
        $this->formLocation = '';
        $this->formWitnesses = '';
        $this->formActionsTaken = '';
        $this->formSeverity = '';
    }
}; ?>

<div>
    @php
        $user = $this->getUser();
        $reports = $this->getReports();
        $riskScore = $user->disciplineRiskScore();
    @endphp

    <flux:card class="w-full">
        <div class="flex items-center gap-3">
            <flux:heading size="md">Discipline Reports</flux:heading>
            <flux:spacer />

            @if($riskScore['total'] > 0)
                <flux:badge color="{{ \App\Models\User::riskScoreColor($riskScore['total']) }}" size="sm"
                    x-data x-tooltip.raw="7d: {{ $riskScore['7d'] }} | 30d: {{ $riskScore['30d'] }} | 90d: {{ $riskScore['90d'] }}">
                    Risk: {{ $riskScore['total'] }}
                </flux:badge>
            @endif

            @if($isStaffViewing)
                <flux:button size="xs" variant="primary" wire:click="openCreateModal">
                    New Report
                </flux:button>
            @endif
        </div>

        <flux:separator variant="subtle" class="my-2" />

        @if($reports->isEmpty())
            <flux:text variant="subtle" class="py-4 text-center">No discipline reports.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Description</flux:table.column>
                    <flux:table.column>Location</flux:table.column>
                    <flux:table.column>Severity</flux:table.column>
                    @if($isStaffViewing)
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Reporter</flux:table.column>
                    @endif
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($reports as $report)
                        <flux:table.row>
                            <flux:table.cell>
                                {{ ($report->published_at ?? $report->created_at)->format('M j, Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text class="truncate max-w-xs">{{ Str::limit($report->description, 60) }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $report->location->color() }}" size="sm">
                                    {{ $report->location->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $report->severity->color() }}" size="sm">
                                    {{ $report->severity->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            @if($isStaffViewing)
                                <flux:table.cell>
                                    <flux:badge color="{{ $report->status->color() }}" size="sm">
                                        {{ $report->status->label() }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $report->reporter->name }}</flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <div class="flex gap-1 justify-end">
                                    <flux:button size="xs" variant="ghost" wire:click="viewReport({{ $report->id }})">
                                        View
                                    </flux:button>
                                    @if($isStaffViewing && $report->isDraft())
                                        <flux:button size="xs" variant="ghost" wire:click="openEditModal({{ $report->id }})">
                                            Edit
                                        </flux:button>
                                        @can('publish', $report)
                                            <flux:button size="xs" variant="primary" wire:click="publishReport({{ $report->id }})"
                                                wire:confirm="Are you sure you want to publish this report? This will notify the user and cannot be undone.">
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
        @endif
    </flux:card>

    {{-- Create Report Modal --}}
    <flux:modal name="create-report-modal" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg">Create Discipline Report</flux:heading>
            <flux:text variant="subtle">Report about: {{ $user->name }}</flux:text>

            <flux:field>
                <flux:label>What Happened</flux:label>
                <flux:textarea wire:model="formDescription" rows="4" placeholder="Describe the incident..." />
                <flux:error name="formDescription" />
            </flux:field>

            <flux:field>
                <flux:label>Where Did This Happen</flux:label>
                <flux:select wire:model="formLocation" placeholder="Select location...">
                    @foreach(ReportLocation::cases() as $loc)
                        <flux:select.option value="{{ $loc->value }}">{{ $loc->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="formLocation" />
            </flux:field>

            <flux:field>
                <flux:label>Witnesses</flux:label>
                <flux:input wire:model="formWitnesses" placeholder="Who else saw what happened? (optional)" />
            </flux:field>

            <flux:field>
                <flux:label>Actions Taken</flux:label>
                <flux:textarea wire:model="formActionsTaken" rows="3" placeholder="What actions were taken in response?" />
                <flux:error name="formActionsTaken" />
            </flux:field>

            <flux:field>
                <flux:label>Severity</flux:label>
                <flux:radio.group wire:model="formSeverity">
                    @foreach(ReportSeverity::cases() as $sev)
                        <flux:radio value="{{ $sev->value }}" label="{{ $sev->label() }} ({{ $sev->points() }}pt)" />
                    @endforeach
                </flux:radio.group>
                <flux:error name="formSeverity" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('create-report-modal').close()">Cancel</flux:button>
                <flux:button wire:click="createReport" variant="primary">Create Report</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit Report Modal --}}
    <flux:modal name="edit-report-modal" class="w-full md:w-1/2 xl:w-1/3">
        <div class="space-y-6">
            <flux:heading size="lg">Edit Discipline Report</flux:heading>

            <flux:field>
                <flux:label>What Happened</flux:label>
                <flux:textarea wire:model="formDescription" rows="4" />
                <flux:error name="formDescription" />
            </flux:field>

            <flux:field>
                <flux:label>Where Did This Happen</flux:label>
                <flux:select wire:model="formLocation">
                    @foreach(ReportLocation::cases() as $loc)
                        <flux:select.option value="{{ $loc->value }}">{{ $loc->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="formLocation" />
            </flux:field>

            <flux:field>
                <flux:label>Witnesses</flux:label>
                <flux:input wire:model="formWitnesses" />
            </flux:field>

            <flux:field>
                <flux:label>Actions Taken</flux:label>
                <flux:textarea wire:model="formActionsTaken" rows="3" />
                <flux:error name="formActionsTaken" />
            </flux:field>

            <flux:field>
                <flux:label>Severity</flux:label>
                <flux:radio.group wire:model="formSeverity">
                    @foreach(ReportSeverity::cases() as $sev)
                        <flux:radio value="{{ $sev->value }}" label="{{ $sev->label() }} ({{ $sev->points() }}pt)" />
                    @endforeach
                </flux:radio.group>
                <flux:error name="formSeverity" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('edit-report-modal').close()">Cancel</flux:button>
                <flux:button wire:click="updateReport" variant="primary">Save Changes</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- View Report Modal --}}
    <flux:modal name="view-report-modal" class="w-full md:w-1/2 xl:w-1/3">
        @if($viewingReportId)
            @php $viewReport = \App\Models\DisciplineReport::with(['reporter', 'publisher'])->find($viewingReportId); @endphp
            @if($viewReport)
                <div class="space-y-4">
                    <flux:heading size="lg">Discipline Report</flux:heading>

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

                    @if($isStaffViewing)
                        <flux:separator variant="subtle" />
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:text class="font-medium text-sm">Status</flux:text>
                                <flux:badge color="{{ $viewReport->status->color() }}">{{ $viewReport->status->label() }}</flux:badge>
                            </div>
                            <div>
                                <flux:text class="font-medium text-sm">Reporter</flux:text>
                                <flux:text>{{ $viewReport->reporter->name }}</flux:text>
                            </div>
                        </div>
                        @if($viewReport->publisher)
                            <div>
                                <flux:text class="font-medium text-sm">Published By</flux:text>
                                <flux:text>{{ $viewReport->publisher->name }} on {{ $viewReport->published_at->format('M j, Y g:i A') }}</flux:text>
                            </div>
                        @endif
                    @endif

                    <div>
                        <flux:text class="font-medium text-sm">Created</flux:text>
                        <flux:text>{{ $viewReport->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                </div>
            @endif
        @endif
    </flux:modal>
</div>
