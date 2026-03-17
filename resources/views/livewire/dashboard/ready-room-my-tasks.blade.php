<?php

use App\Enums\StaffDepartment;
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

<flux:card>
    <flux:heading class="mb-4">My Assigned Tasks</flux:heading>

    @if($this->tasksBySection->isEmpty())
        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
            <flux:icon name="clipboard-document-check" class="mx-auto h-10 w-10 mb-2" />
            <flux:text variant="subtle" class="text-sm">No tasks assigned to you.</flux:text>
        </div>
    @else
        <div class="space-y-4">
            @foreach($this->tasksBySection as $section => $tasks)
                <div wire:key="task-section-{{ $section }}">
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                        {{ StaffDepartment::tryFrom($section)?->label() ?? ucfirst($section) }}
                    </flux:text>
                    <div class="space-y-1">
                        @foreach($tasks as $task)
                            <livewire:task.show-task :task="$task" wire:key="my-task-{{ $task->id }}" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</flux:card>
