<?php

use App\Enums\ApplicationQuestionCategory;
use App\Enums\ApplicationQuestionType;
use App\Models\ApplicationQuestion;
use App\Models\StaffPosition;
use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Create form
    public string $questionText = '';
    public string $type = 'short_text';
    public string $category = 'core';
    public ?int $staffPositionId = null;
    public string $selectOptionsInput = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    // Edit form
    public ?int $editingId = null;
    public string $editQuestionText = '';
    public string $editType = 'short_text';
    public string $editCategory = 'core';
    public ?int $editStaffPositionId = null;
    public string $editSelectOptionsInput = '';
    public int $editSortOrder = 0;
    public bool $editIsActive = true;

    public function getQuestionsProperty()
    {
        return ApplicationQuestion::with('staffPosition')
            ->ordered()
            ->paginate(20);
    }

    public function getPositionsProperty()
    {
        return StaffPosition::ordered()->get();
    }

    public function createQuestion(): void
    {
        $this->authorize('manage-application-questions');

        $this->validate([
            'questionText' => 'required|string|min:5',
            'type' => 'required|string|in:' . implode(',', array_column(ApplicationQuestionType::cases(), 'value')),
            'category' => 'required|string|in:' . implode(',', array_column(ApplicationQuestionCategory::cases(), 'value')),
            'staffPositionId' => 'nullable|exists:staff_positions,id',
            'sortOrder' => 'required|integer|min:0',
        ]);

        $selectOptions = null;
        if ($this->type === 'select' && $this->selectOptionsInput) {
            $selectOptions = array_map('trim', explode(',', $this->selectOptionsInput));
            $selectOptions = array_filter($selectOptions);
            $selectOptions = array_values($selectOptions);
        }

        ApplicationQuestion::create([
            'question_text' => $this->questionText,
            'type' => $this->type,
            'category' => $this->category,
            'staff_position_id' => $this->category === 'position_specific' ? $this->staffPositionId : null,
            'select_options' => $selectOptions,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ]);

        $this->resetCreateForm();
        Flux::modal('create-question')->close();
        Flux::toast('Question created.', 'Success', variant: 'success');
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('manage-application-questions');
        $question = ApplicationQuestion::findOrFail($id);
        $this->editingId = $id;
        $this->editQuestionText = $question->question_text;
        $this->editType = $question->type->value;
        $this->editCategory = $question->category->value;
        $this->editStaffPositionId = $question->staff_position_id;
        $this->editSelectOptionsInput = $question->select_options ? implode(', ', $question->select_options) : '';
        $this->editSortOrder = $question->sort_order;
        $this->editIsActive = $question->is_active;
        Flux::modal('edit-question')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('manage-application-questions');

        $this->validate([
            'editQuestionText' => 'required|string|min:5',
            'editType' => 'required|string|in:' . implode(',', array_column(ApplicationQuestionType::cases(), 'value')),
            'editCategory' => 'required|string|in:' . implode(',', array_column(ApplicationQuestionCategory::cases(), 'value')),
            'editStaffPositionId' => 'nullable|exists:staff_positions,id',
            'editSortOrder' => 'required|integer|min:0',
        ]);

        $question = ApplicationQuestion::findOrFail($this->editingId);

        $selectOptions = null;
        if ($this->editType === 'select' && $this->editSelectOptionsInput) {
            $selectOptions = array_map('trim', explode(',', $this->editSelectOptionsInput));
            $selectOptions = array_filter($selectOptions);
            $selectOptions = array_values($selectOptions);
        }

        $question->update([
            'question_text' => $this->editQuestionText,
            'type' => $this->editType,
            'category' => $this->editCategory,
            'staff_position_id' => $this->editCategory === 'position_specific' ? $this->editStaffPositionId : null,
            'select_options' => $selectOptions,
            'sort_order' => $this->editSortOrder,
            'is_active' => $this->editIsActive,
        ]);

        Flux::modal('edit-question')->close();
        Flux::toast('Question updated.', 'Success', variant: 'success');
    }

    public function deleteQuestion(int $id): void
    {
        $this->authorize('manage-application-questions');
        ApplicationQuestion::findOrFail($id)->delete();
        Flux::toast('Question deleted.', 'Deleted', variant: 'success');
    }

    private function resetCreateForm(): void
    {
        $this->questionText = '';
        $this->type = 'short_text';
        $this->category = 'core';
        $this->staffPositionId = null;
        $this->selectOptionsInput = '';
        $this->sortOrder = 0;
        $this->isActive = true;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg">Application Questions</flux:heading>
        <flux:button variant="primary" icon="plus" size="sm" x-on:click="$flux.modal('create-question').show()">Add Question</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Order</flux:table.column>
            <flux:table.column>Question</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Category</flux:table.column>
            <flux:table.column>Position</flux:table.column>
            <flux:table.column>Active</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->questions as $question)
                <flux:table.row wire:key="q-{{ $question->id }}">
                    <flux:table.cell>{{ $question->sort_order }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate">{{ Str::limit($question->question_text, 60) }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $question->type->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" color="purple">{{ $question->category->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell>{{ $question->staffPosition?->title ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if($question->is_active)
                            <flux:badge size="sm" color="emerald">Active</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditModal({{ $question->id }})" />
                            <flux:button variant="ghost" size="sm" icon="trash" wire:click="deleteQuestion({{ $question->id }})" wire:confirm="Are you sure you want to delete this question?" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center">
                        <flux:text variant="subtle">No questions configured yet.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">{{ $this->questions->links() }}</div>

    {{-- Create Modal --}}
    <flux:modal name="create-question" class="w-full lg:w-1/2">
        <flux:heading size="lg" class="mb-4">Add Application Question</flux:heading>
        <form wire:submit="createQuestion" class="space-y-4">
            <flux:field>
                <flux:label>Question Text <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="questionText" rows="3" />
                <flux:error name="questionText" />
            </flux:field>
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model.live="type">
                        @foreach(ApplicationQuestionType::cases() as $t)
                            <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model.live="category">
                        @foreach(ApplicationQuestionCategory::cases() as $c)
                            <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            @if($category === 'position_specific')
                <flux:field>
                    <flux:label>Position</flux:label>
                    <flux:select wire:model="staffPositionId">
                        <flux:select.option value="">Select a position...</flux:select.option>
                        @foreach($this->positions as $pos)
                            <flux:select.option value="{{ $pos->id }}">{{ $pos->title }} ({{ $pos->department->label() }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif
            @if($type === 'select')
                <flux:field>
                    <flux:label>Dropdown Options</flux:label>
                    <flux:description>Comma-separated list of options</flux:description>
                    <flux:input wire:model="selectOptionsInput" placeholder="Option 1, Option 2, Option 3" />
                </flux:field>
            @endif
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Sort Order</flux:label>
                    <flux:input type="number" wire:model="sortOrder" min="0" />
                </flux:field>
                <flux:field class="flex items-end">
                    <flux:checkbox wire:model="isActive" label="Active" />
                </flux:field>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('create-question').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create Question</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal name="edit-question" class="w-full lg:w-1/2">
        <flux:heading size="lg" class="mb-4">Edit Application Question</flux:heading>
        <form wire:submit="saveEdit" class="space-y-4">
            <flux:field>
                <flux:label>Question Text <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="editQuestionText" rows="3" />
                <flux:error name="editQuestionText" />
            </flux:field>
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model.live="editType">
                        @foreach(ApplicationQuestionType::cases() as $t)
                            <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model.live="editCategory">
                        @foreach(ApplicationQuestionCategory::cases() as $c)
                            <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            @if($editCategory === 'position_specific')
                <flux:field>
                    <flux:label>Position</flux:label>
                    <flux:select wire:model="editStaffPositionId">
                        <flux:select.option value="">Select a position...</flux:select.option>
                        @foreach($this->positions as $pos)
                            <flux:select.option value="{{ $pos->id }}">{{ $pos->title }} ({{ $pos->department->label() }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif
            @if($editType === 'select')
                <flux:field>
                    <flux:label>Dropdown Options</flux:label>
                    <flux:description>Comma-separated list of options</flux:description>
                    <flux:input wire:model="editSelectOptionsInput" placeholder="Option 1, Option 2, Option 3" />
                </flux:field>
            @endif
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Sort Order</flux:label>
                    <flux:input type="number" wire:model="editSortOrder" min="0" />
                </flux:field>
                <flux:field class="flex items-end">
                    <flux:checkbox wire:model="editIsActive" label="Active" />
                </flux:field>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('edit-question').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
