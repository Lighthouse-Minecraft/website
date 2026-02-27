<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use Livewire\Volt\Component;

new class extends Component {
    protected $listeners = ['taskUpdated' => '$refresh'];

    #[\Livewire\Attributes\Computed]
    public function tasksBySection()
    {
        return Task::where('assigned_to_user_id', auth()->id())
            ->whereIn('status', [TaskStatus::Pending, TaskStatus::Completed])
            ->with('assignedTo')
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('section_key');
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="lg">My Assigned Tasks</flux:heading>

    @if($this->tasksBySection->isEmpty())
        <flux:card>
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <flux:icon name="clipboard-document-check" class="mx-auto h-12 w-12 mb-3" />
                <p>No tasks assigned to you.</p>
            </div>
        </flux:card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($this->tasksBySection as $section => $tasks)
                <flux:card wire:key="task-section-{{ $section }}">
                    <flux:heading class="mb-4">{{ ucfirst($section) }}</flux:heading>
                    @foreach($tasks as $task)
                        <livewire:task.show-task :task="$task" wire:key="my-task-{{ $task->id }}" />
                    @endforeach
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
