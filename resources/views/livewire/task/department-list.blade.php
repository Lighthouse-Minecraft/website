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
    public $tasks;
    public $completedTasks;
    public $archivedTasks;

    protected $listeners = ['taskUpdated' => 'loadTasks'];

    public function mount(Meeting $meeting, string $section_key) {
        $this->meeting = $meeting;
        $this->section_key = $section_key;

        $this->loadTasks();
    }

    public function loadTasks()
    {
        $this->tasks = Task::where('section_key', $this->section_key)
            ->where('status', TaskStatus::Pending)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->completedTasks = Task::where('section_key', $this->section_key)
            ->where('status', TaskStatus::Completed)
            ->orderBy('completed_at', 'asc')
            ->get();

        // Don't show archived tasks if we're not in a meeting
        if ($this->meeting?->id) {
            $this->archivedTasks = Task::where('section_key', $this->section_key)
                ->where('status', TaskStatus::Archived)
                ->where('archived_meeting_id', $this->meeting->id)
                ->orderBy('archived_at', 'asc')
                ->get();
        }
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
            'created_by' => auth()->id(),
        ]);

        $task->save();

        Flux::toast('Task created', 'Success', variant: 'success');
        $this->taskName = null; // Reset the task name input
        $this->loadTasks(); // Refresh the task list
    }
}; ?>

<div class="space-y-6">
    <flux:heading class="mb-4">{{ ucfirst($section_key) }} Tasks</flux:heading>

    <flux:card>
        @if (! $completedTasks->isEmpty())
            <flux:heading class="mb-4">Recently Completed Tasks</flux:heading>
            @foreach ($completedTasks as $task)
                <livewire:task.show-task :task="$task" :meeting="$meeting" wire:key="task-{{ $task->id }}->completed" />
            @endforeach
        @endif

        @if(! $tasks->isEmpty())
            <flux:heading class="my-4">In Progress Tasks</flux:heading>
            @foreach ($tasks as $task)
                <livewire:task.show-task :task="$task" :meeting="$meeting" wire:key="task-{{ $task->id }}->in_progress" />
            @endforeach
        @endif

        @if($archivedTasks && ! $archivedTasks->isEmpty())
            <flux:heading class="my-4">Archived Tasks This Meeting</flux:heading>
            @foreach ($archivedTasks as $task)
                <livewire:task.show-task :task="$task" :meeting="$meeting" wire:key="task-{{ $task->id }}->archived" />
            @endforeach
        @endif
    </flux:card>

    <form wire:submit.prevent="addTask">
        <flux:input.group>
            <flux:input wire:model="taskName" placeholder="Task Name" />

            <flux:button icon="plus" wire:click="addTask">Add Task</flux:button>
        </flux:input.group>
    </form>
</div>
