<?php

use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public Task $task;
    public ?Meeting $meeting = null;
    public string $editName = '';
    public ?int $editAssignedTo = null;

    public function mount(Task $task, ?Meeting $meeting = null) {
        $this->task = $task->load('assignedTo');
        $this->meeting = $meeting;
        $this->editName = $task->name;
        $this->editAssignedTo = $task->assigned_to_user_id;
    }

    public function toggleCompletion() {
        if ($this->task->status === TaskStatus::Completed) {
            // Mark as pending again
            $this->task->update([
                'status' => TaskStatus::Pending,
                'completed_by' => null,
                'completed_at' => null,
                'completed_meeting_id' => null,
            ]);
        } else {
            // Mark as completed
            $this->task->update([
                'status' => TaskStatus::Completed,
                'completed_by' => auth()->id(),
                'completed_at' => now(),
                'completed_meeting_id' => $this->meeting?->id,
            ]);
        }

        $this->task->refresh();
        $this->dispatch('taskUpdated');
    }

    public function openEditModal()
    {
        $this->editName = $this->task->name;
        $this->editAssignedTo = $this->task->assigned_to_user_id;
        $this->modal("edit-task-{$this->task->id}")->show();
    }

    public function updateTask()
    {
        $this->authorize('update', $this->task);

        $this->validate([
            'editName' => 'required|string|max:255',
            'editAssignedTo' => 'nullable|exists:users,id',
        ]);

        $this->task->update([
            'name' => $this->editName,
            'assigned_to_user_id' => $this->editAssignedTo,
        ]);

        $this->task->refresh()->load('assignedTo');
        $this->modal("edit-task-{$this->task->id}")->close();
        $this->dispatch('taskUpdated');
        Flux::toast('Task updated.', variant: 'success');
    }

    public function getIsCompletedProperty()
    {
        return $this->task->status === TaskStatus::Completed;
    }

    public function getIsArchivedProperty()
    {
        return $this->task->status === TaskStatus::Archived;
    }

    public function getStaffUsersProperty()
    {
        return User::whereNotNull('staff_department')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function markAsArchived()
    {
        $this->task->update(['archived_at' => now(), 'archived_meeting_id' => $this->meeting?->id, 'status' => TaskStatus::Archived]);
        $this->dispatch('taskUpdated');
    }
}; ?>

<div class="flex items-center">
    @if ($this->isArchived)
        <flux:text class="mr-2 flex" variant="strong"><flux:icon name="check-circle" class="mr-2 text-emerald-300" /> {{ $task->name }}</flux:text>
    @else
        <flux:checkbox class="mb-2"
            :label="$task->name"
            :checked="$this->isCompleted"
            wire:change="toggleCompletion"
        />
    @endif

    @if($task->assignedTo)
        <flux:badge size="sm" color="zinc" class="ml-2">{{ $task->assignedTo->name }}</flux:badge>
    @endif

    <flux:spacer />

    <div class="flex items-center gap-1">
        @if(! $this->isArchived)
            @can('update', $task)
                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditModal" />
            @endcan
        @endif

        @if ($this->isCompleted && $this->meeting?->id)
            <flux:button size="xs" color="indigo" wire:click="markAsArchived">Archive</flux:button>
        @endif
    </div>

    {{-- Edit Task Modal --}}
    <flux:modal name="edit-task-{{ $task->id }}" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Edit Task</flux:heading>
        </div>

        <flux:input wire:model="editName" label="Task Name" required />

        <flux:select wire:model="editAssignedTo" label="Assign To" placeholder="Unassigned">
            <flux:select.option value="">Unassigned</flux:select.option>
            @foreach($this->staffUsers as $staffUser)
                <flux:select.option value="{{ $staffUser->id }}">{{ $staffUser->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button wire:click="updateTask" variant="primary">Save</flux:button>
        </div>
    </flux:modal>
</div>
