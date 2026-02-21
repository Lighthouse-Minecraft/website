<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Thread;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $perPage = 25;
    public string $filterAction = '';
    public array $distinctActions = [];

    /**
     * Populate the component's $distinctActions with all distinct activity action names.
     *
     * Loads action values from the ActivityLog model, ordered alphabetically, into the
     * component's distinctActions array for use by the action filter.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->distinctActions = ActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();
    }

    /**
     * Reset pagination to the first page after the action filter changes.
     *
     * @return void
     */
    public function updatedFilterAction()
    {
        $this->resetPage();
    }

    /****
     * Retrieve a paginated list of activity log entries with their causer and subject relations,
     * optionally filtered by the current action and ordered by newest first.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator A paginator of ActivityLog models with `causer` and `subject` relations loaded.
     */
    #[\Livewire\Attributes\Computed]
    public function activities()
    {
        return ActivityLog::query()
            ->with(['causer', 'subject'])
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->latest()
            ->paginate($this->perPage);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:heading size="xl">User Activity Log</flux:heading>
        <flux:spacer />
        <flux:select wire:model.live="filterAction" size="sm" class="w-56">
            <flux:select.option value="">All Actions</flux:select.option>
            @foreach($distinctActions as $action)
                <flux:select.option value="{{ $action }}">{{ Str::of($action)->replace('_', ' ')->title() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->activities">
        <flux:table.columns>
            <flux:table.column>Date / Time</flux:table.column>
            <flux:table.column>Subject</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>By User</flux:table.column>
            <flux:table.column>Details</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($this->activities as $activity)
                <flux:table.row wire:key="activity-{{ $activity->id }}" :key="$activity->id">
                    @php
                        $tz = auth()->user()->timezone ?? 'UTC';
                        $localTime = $activity->created_at->setTimezone($tz);
                    @endphp
                    <flux:table.cell class="text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400"
                        title="{{ $localTime->format('Y-m-d H:i:s T') }}">
                        {{ $localTime->format('M j, Y') }}<br>
                        {{ $localTime->format('g:i A') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @php $subject = $activity->subject; @endphp
                        @if ($subject instanceof User)
                            <flux:link href="{{ route('profile.show', $subject) }}">{{ $subject->name }}</flux:link>
                        @elseif ($subject instanceof Thread)
                            <flux:link href="{{ route('tickets.show', ['thread' => $subject->id]) }}">{{ $subject->subject }}</flux:link>
                        @elseif ($subject)
                            {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                        @else
                            <span class="text-zinc-500">[deleted]</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:badge size="sm" variant="pill">{{ Str::of($activity->action)->replace('_', ' ')->title() }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($activity->causer)
                            <flux:link href="{{ route('profile.show', $activity->causer) }}">{{ $activity->causer->name }}</flux:link>
                        @else
                            <em class="text-zinc-500">System</em>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="max-w-sm text-sm truncate text-zinc-400">
                        {{ $activity->description }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>