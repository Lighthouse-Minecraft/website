<?php

use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Task;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public string $section_key;
    public ?string $taskName = null;

    public function mount(Meeting $meeting, string $section_key) {
        $this->meeting = $meeting;
        $this->section_key = $section_key;
    }

    public function addTask() {
        $this->authorize('create', App\Models\Task::class);

        if (empty($this->taskName)) {
            Flux::toast('Task name cannot be empty.', 'Error', variant: 'warning');
            return;
        }

        $task = new Task([
            'name' => $this->taskName,
            'section_key' => $this->section_key,
            'status' => TaskStatus::Pending,
            'assigned_meeting_id' => $this->meeting->id,
        ]);

        $task->save();

        Flux::toast('Task created', 'Success', variant: 'success');
        $this->taskName = null; // Reset the task name input
    }
}; ?>

<div>
    <flux:heading class="mb-4">{{ ucfirst($section_key) }} Tasks</flux:heading>

    <flux:input.group>
        <flux:input wire:model="taskName" placeholder="Task Name" />

        <flux:button icon="plus">Add Task</flux:button>
    </flux:input.group>
</div>
