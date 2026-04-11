<?php

use App\Actions\CreateCommunityQuestion;
use App\Actions\UpdateCommunityQuestion;
use App\Enums\CommunityQuestionStatus;
use App\Models\CommunityQuestion;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Flux\Flux;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    // Create/Edit form
    public ?int $editingId = null;
    public string $formQuestionText = '';
    public string $formDescription = '';
    public string $formStatus = 'draft';
    public ?string $formStartDate = null;
    public ?string $formEndDate = null;

    protected function userTimezone(): string
    {
        return auth()->user()->timezone ?? 'America/New_York';
    }

    protected function toUtc(?string $datetime): ?Carbon
    {
        if (! filled($datetime)) {
            return null;
        }

        return Carbon::parse($datetime, new CarbonTimeZone($this->userTimezone()))->utc();
    }

    protected function toLocal(?\DateTimeInterface $datetime): ?string
    {
        if (! $datetime) {
            return null;
        }

        return Carbon::instance($datetime)->setTimezone($this->userTimezone())->format('Y-m-d\TH:i');
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getQuestionsProperty()
    {
        $allowedColumns = ['created_at', 'question_text', 'status', 'start_date', 'end_date'];
        $sortBy = in_array($this->sortBy, $allowedColumns, true) ? $this->sortBy : 'created_at';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        return CommunityQuestion::orderBy($sortBy, $sortDirection)
            ->withCount(['responses', 'approvedResponses as approved_responses_count'])
            ->with('creator')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        $this->authorize('manage-community-stories');

        $this->reset(['editingId', 'formQuestionText', 'formDescription', 'formStatus', 'formStartDate', 'formEndDate']);
        Flux::modal('question-form-modal')->show();
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('manage-community-stories');

        $question = CommunityQuestion::findOrFail($id);
        $this->editingId = $id;
        $this->formQuestionText = $question->question_text;
        $this->formDescription = $question->description ?? '';
        $this->formStatus = $question->status->value;
        $this->formStartDate = $this->toLocal($question->start_date);
        $this->formEndDate = $this->toLocal($question->end_date);

        Flux::modal('question-form-modal')->show();
    }

    public function saveQuestion(): void
    {
        $this->authorize('manage-community-stories');

        $this->validate([
            'formQuestionText' => 'required|string|min:10|max:1000',
            'formDescription' => 'nullable|string|max:2000',
            'formStatus' => 'required|in:draft,scheduled,active,archived',
            'formStartDate' => 'nullable|date',
            'formEndDate' => 'nullable|date|after_or_equal:formStartDate',
        ]);

        $status = CommunityQuestionStatus::from($this->formStatus);
        $startDate = $this->toUtc($this->formStartDate);
        $endDate = $this->toUtc($this->formEndDate);

        if ($this->editingId) {
            $question = CommunityQuestion::findOrFail($this->editingId);
            UpdateCommunityQuestion::run($question, auth()->user(), [
                'question_text' => $this->formQuestionText,
                'description' => $this->formDescription ?: null,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            Flux::toast('Question updated.', 'Updated', variant: 'success');
        } else {
            CreateCommunityQuestion::run(
                auth()->user(),
                $this->formQuestionText,
                $this->formDescription ?: null,
                $status,
                $startDate,
                $endDate,
            );
            Flux::toast('Question created.', 'Created', variant: 'success');
        }

        Flux::modal('question-form-modal')->close();
        $this->reset(['editingId', 'formQuestionText', 'formDescription', 'formStatus', 'formStartDate', 'formEndDate']);
    }

    public function deleteQuestion(int $id): void
    {
        $question = CommunityQuestion::findOrFail($id);
        $this->authorize('delete', $question);

        $question->delete();
        Flux::toast('Question deleted.', 'Deleted', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Community Questions</flux:heading>

    <flux:table :paginate="$this->questions">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'question_text'" :direction="$sortDirection" wire:click="sort('question_text')">Question</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'start_date'" :direction="$sortDirection" wire:click="sort('start_date')">Start Date</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'end_date'" :direction="$sortDirection" wire:click="sort('end_date')">End Date</flux:table.column>
            <flux:table.column>Responses</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->questions as $question)
                <flux:table.row wire:key="q-row-{{ $question->id }}">
                    <flux:table.cell class="font-medium max-w-xs truncate">{{ Str::limit($question->question_text, 60) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $question->status->color() }}">{{ $question->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $question->start_date?->format('M j, Y g:ia') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $question->end_date?->format('M j, Y g:ia') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $question->responses_count }}</flux:table.cell>
                    <flux:table.cell>{{ $question->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button size="sm" icon="pencil-square" wire:click="openEditModal({{ $question->id }})">Edit</flux:button>
                            @if($question->approved_responses_count === 0)
                                <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteQuestion({{ $question->id }})" wire:confirm="Delete this question? This cannot be undone.">Delete</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-gray-500">No community questions yet. Create your first one!</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:button variant="primary" wire:click="openCreateModal">Create Question</flux:button>
    </div>

    {{-- Create/Edit Modal --}}
    <flux:modal name="question-form-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">{{ $editingId ? 'Edit' : 'Create' }} Question</flux:heading>
        <form wire:submit.prevent="saveQuestion">
            <div class="space-y-6">
                <flux:textarea label="Question Text" wire:model="formQuestionText" rows="3" required placeholder="What question would you ask the community?" />

                <flux:textarea label="Description (optional)" wire:model="formDescription" rows="2" placeholder="Optional context or guidance for the question" />

                <flux:select label="Status" wire:model="formStatus">
                    @foreach(CommunityQuestionStatus::cases() as $status)
                        <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:field>
                    <flux:label>Start Date</flux:label>
                    <flux:input wire:model="formStartDate" type="datetime-local" />
                    <flux:description>When the question becomes active. Required for scheduled questions.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>End Date</flux:label>
                    <flux:input wire:model="formEndDate" type="datetime-local" />
                    <flux:description>When the question will be automatically archived.</flux:description>
                </flux:field>

                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save Changes' : 'Create' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
