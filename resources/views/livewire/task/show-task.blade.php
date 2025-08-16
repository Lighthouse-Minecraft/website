<?php

use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Task;
use Livewire\Volt\Component;

new class extends Component {
    public Task $task;
    public ?Meeting $meeting = null;

    public function mount(Task $task, ?Meeting $meeting = null) {
        $this->task = $task;
        $this->meeting = $meeting;
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

    public function getIsCompletedProperty()
    {
        return $this->task->status === TaskStatus::Completed;
    }


    public function getIsArchivedProperty()
    {
        return $this->task->status === TaskStatus::Archived;
    }


    public function markAsArchived()
    {
        $this->task->update(['archived_at' => now(), 'archived_meeting_id' => $this->meeting?->id, 'status' => TaskStatus::Archived]);
        $this->dispatch('taskUpdated');
    }
}; ?>

<div class="flex">
    @if ($this->isArchived)
        <flux:text class="mr-2 flex" variant="strong"><flux:icon name="check-circle" class="mr-2 text-emerald-300" /> {{ $task->name }}</flux:text>
    @else
        <flux:checkbox class="mb-2"
            :label="$task->name"
            :checked="$this->isCompleted"
            wire:change="toggleCompletion"
        />
    @endif

    <flux:spacer />

    <div class="text-right">
        @if ($this->isCompleted)
            <flux:button size="xs" color="indigo" wire:click="markAsArchived">Archive</flux:button>
        @endif
    </div>
</div>
