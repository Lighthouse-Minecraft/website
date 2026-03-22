<?php

use App\Actions\CreateDisciplineReport;
use App\Actions\CreateTopic;
use App\Actions\PublishDisciplineReport;
use App\Actions\UpdateDisciplineReport;
use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\ReportCategory;
use App\Models\Thread;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
    public string $formCategory = '';

    // Editing state
    #[Locked]
    public ?int $editingReportId = null;

    // Viewing state
    #[Locked]
    public ?int $viewingReportId = null;

    // Topic creation state
    public string $topicSubject = '';

    public function mount(User $user): void
    {
        $authUser = Auth::user();
        $isStaff = $authUser->hasRole('Staff Access');
        $isSelf = $authUser->id === $user->id;
        $isParent = $authUser->children()->where('child_user_id', $user->id)->exists();

        if (! $isStaff && ! $isSelf && ! $isParent) {
            abort(403);
        }

        $this->userId = $user->id;
        $this->isStaffViewing = $isStaff;
    }

    public function getUserProperty(): User
    {
        return User::findOrFail($this->userId);
    }

    public function getReportsProperty()
    {
        $query = DisciplineReport::where('subject_user_id', $this->userId)
            ->with('category')
            ->latest();

        if (! $this->isStaffViewing) {
            $query->published();
        }

        return $query->get();
    }

    public function getRiskScoreProperty(): array
    {
        return $this->user->disciplineRiskScore();
    }

    public function getCategoriesProperty()
    {
        return ReportCategory::orderBy('name')->get();
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
            'formLocation' => ['required', 'string', Rule::enum(ReportLocation::class)],
            'formActionsTaken' => 'required|string|min:5',
            'formSeverity' => ['required', 'string', Rule::enum(ReportSeverity::class)],
            'formCategory' => 'nullable|string|exists:report_categories,id',
        ]);

        $subject = $this->user;
        $category = $this->formCategory ? ReportCategory::find($this->formCategory) : null;

        CreateDisciplineReport::run(
            $subject,
            Auth::user(),
            $this->formDescription,
            ReportLocation::from($this->formLocation),
            $this->formActionsTaken,
            ReportSeverity::from($this->formSeverity),
            $this->formWitnesses ?: null,
            $category,
        );

        $this->resetForm();
        Flux::modal('create-report-modal')->close();
        Flux::toast('Staff report created.', 'Report Created', variant: 'success');
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
        $this->formCategory = (string) ($report->report_category_id ?? '');

        Flux::modal('edit-report-modal')->show();
    }

    public function updateReport(): void
    {
        $report = DisciplineReport::findOrFail($this->editingReportId);
        $this->authorize('update', $report);

        $this->validate([
            'formDescription' => 'required|string|min:10',
            'formLocation' => ['required', 'string', Rule::enum(ReportLocation::class)],
            'formActionsTaken' => 'required|string|min:5',
            'formSeverity' => ['required', 'string', Rule::enum(ReportSeverity::class)],
            'formCategory' => 'nullable|string|exists:report_categories,id',
        ]);

        $category = $this->formCategory ? ReportCategory::find($this->formCategory) : null;

        UpdateDisciplineReport::run(
            $report,
            Auth::user(),
            $this->formDescription,
            ReportLocation::from($this->formLocation),
            $this->formActionsTaken,
            ReportSeverity::from($this->formSeverity),
            $this->formWitnesses ?: null,
            $category,
        );

        $this->resetForm();
        $this->editingReportId = null;
        Flux::modal('edit-report-modal')->close();
        Flux::toast('Staff report updated.', 'Report Updated', variant: 'success');
    }

    public function publishReport(int $reportId): void
    {
        $report = DisciplineReport::findOrFail($reportId);
        $this->authorize('publish', $report);

        PublishDisciplineReport::run($report, Auth::user());

        Flux::toast('Staff report published.', 'Report Published', variant: 'success');
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
        Flux::modal('view-report-modal')->show();
    }

    public function getReportTopicsProperty()
    {
        if (! $this->viewingReportId) {
            return collect();
        }

        $report = DisciplineReport::find($this->viewingReportId);
        if (! $report) {
            return collect();
        }

        return $report->topics()
            ->withCount('messages')
            ->latest()
            ->get()
            ->filter(fn ($topic) => $topic->isVisibleTo(Auth::user()));
    }

    public function openCreateTopicModal(): void
    {
        $report = DisciplineReport::findOrFail($this->viewingReportId);
        $this->authorize('createTopic', [Thread::class, $report]);

        $this->topicSubject = '';
        Flux::modal('create-topic-modal')->show();
    }

    public function startTopic(): void
    {
        $report = DisciplineReport::findOrFail($this->viewingReportId);
        $this->authorize('createTopic', [Thread::class, $report]);

        $this->validate([
            'topicSubject' => 'required|string|min:3|max:255',
        ]);

        $thread = CreateTopic::run($report, Auth::user(), $this->topicSubject);

        $this->topicSubject = '';
        Flux::modal('create-topic-modal')->close();
        Flux::modal('view-report-modal')->close();

        $this->redirect(route('discussions.show', $thread), navigate: true);
    }

    private function resetForm(): void
    {
        $this->formDescription = '';
        $this->formLocation = '';
        $this->formWitnesses = '';
        $this->formActionsTaken = '';
        $this->formSeverity = '';
        $this->formCategory = '';
    }
}; ?>

<div>
    <flux:card class="w-full">
        <div class="flex items-center gap-3">
            <flux:heading size="md">Staff Reports</flux:heading>
            <flux:spacer />

            @if($this->riskScore['total'] > 0)
                <flux:tooltip content="7d: {{ $this->riskScore['7d'] }} | 30d: {{ $this->riskScore['30d'] }} | 90d: {{ $this->riskScore['90d'] }}">
                    <flux:badge color="{{ \App\Models\User::riskScoreColor($this->riskScore['total']) }}" size="sm">
                        Risk: {{ $this->riskScore['total'] }}
                    </flux:badge>
                </flux:tooltip>
            @endif

            @if($isStaffViewing)
                <flux:button size="xs" variant="primary" wire:click="openCreateModal">
                    New Report
                </flux:button>
            @endif
        </div>

        <flux:separator variant="subtle" class="my-2" />

        @if($this->reports->isEmpty())
            <flux:text variant="subtle" class="py-4 text-center">No staff reports.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Category</flux:table.column>
                    <flux:table.column>Description</flux:table.column>
                    <flux:table.column>Severity</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->reports as $report)
                        <flux:table.row wire:key="report-{{ $report->id }}">
                            <flux:table.cell>
                                {{ ($report->published_at ?? $report->created_at)->format('M j, Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($report->category)
                                    <flux:badge color="{{ $report->category->color }}" size="sm">
                                        {{ $report->category->name }}
                                    </flux:badge>
                                @else
                                    <flux:text variant="subtle">—</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    @if($report->isDraft())
                                        <flux:badge color="amber" size="sm">Draft</flux:badge>
                                    @endif
                                    <flux:text class="truncate max-w-xs">{{ Str::limit($report->description, 60) }}</flux:text>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $report->severity->color() }}" size="sm">
                                    {{ $report->severity->label() }}
                                </flux:badge>
                            </flux:table.cell>
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
                                        @elsecan('update', $report)
                                            <flux:text variant="subtle" class="text-xs italic self-center">Another officer must publish</flux:text>
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
            <flux:heading size="lg">Create Staff Report</flux:heading>
            <flux:text variant="subtle">Report about: {{ $this->user->name }}</flux:text>

            <flux:field>
                <flux:label>Category</flux:label>
                <flux:select wire:model="formCategory" placeholder="Select category...">
                    @foreach($this->categories as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

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
                <flux:select wire:model="formSeverity" placeholder="Select severity...">
                    @foreach(ReportSeverity::cases() as $sev)
                        <flux:select.option value="{{ $sev->value }}">{{ $sev->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
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
            <flux:heading size="lg">Edit Staff Report</flux:heading>

            <flux:field>
                <flux:label>Category</flux:label>
                <flux:select wire:model="formCategory" placeholder="Select category...">
                    @foreach($this->categories as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

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
                <flux:select wire:model="formSeverity">
                    @foreach(ReportSeverity::cases() as $sev)
                        <flux:select.option value="{{ $sev->value }}">{{ $sev->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
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
        @if($this->viewingReport)
            @php $viewReport = $this->viewingReport; @endphp
            <div class="space-y-4">
                <flux:heading size="lg">Staff Report</flux:heading>

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

                @if($isStaffViewing)
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
                @else
                    <div>
                        <flux:text class="font-bold text-sm">Date</flux:text>
                        <flux:text>{{ ($viewReport->published_at ?? $viewReport->created_at)->format('M j, Y g:i A') }}</flux:text>
                    </div>
                @endif

                {{-- Discussion Topics Section --}}
                @if($viewReport->isPublished())
                    <flux:separator variant="subtle" />
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <flux:text class="font-bold text-sm">Discussions</flux:text>
                            @can('createTopic', [\App\Models\Thread::class, $viewReport])
                                <flux:button size="xs" variant="primary" wire:click="openCreateTopicModal">
                                    Start Discussion
                                </flux:button>
                            @endcan
                        </div>

                        @if($this->reportTopics->isEmpty())
                            <flux:text variant="subtle" class="text-sm">No discussions yet.</flux:text>
                        @else
                            <div class="space-y-2">
                                @foreach($this->reportTopics as $topic)
                                    <a href="{{ route('discussions.show', $topic) }}" wire:key="report-topic-{{ $topic->id }}" class="flex items-center justify-between rounded border border-zinc-200 dark:border-zinc-700 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                                        <div>
                                            <flux:text class="font-medium text-sm">{{ $topic->subject }}</flux:text>
                                            <flux:text variant="subtle" class="text-xs">{{ $topic->created_at->diffForHumans() }}</flux:text>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($topic->is_locked)
                                                <flux:badge color="red" size="sm">Locked</flux:badge>
                                            @endif
                                            <flux:badge color="zinc" size="sm">{{ $topic->messages_count }} {{ Str::plural('message', $topic->messages_count) }}</flux:badge>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>

    {{-- Create Topic Modal --}}
    <flux:modal name="create-topic-modal" class="w-full md:w-1/3">
        <div class="space-y-4">
            <flux:heading size="lg">Start Discussion</flux:heading>
            <flux:text variant="subtle">Create a new discussion topic related to this report.</flux:text>

            <flux:field>
                <flux:label>Subject</flux:label>
                <flux:input wire:model="topicSubject" placeholder="What would you like to discuss?" />
                <flux:error name="topicSubject" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('create-topic-modal').close()">Cancel</flux:button>
                <flux:button wire:click="startTopic" variant="primary">Create Topic</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
