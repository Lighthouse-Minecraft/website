<?php

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $perPage = 25;

    private const BRIG_ACTIONS = [
        'user_put_in_brig',
        'user_released_from_brig',
        'brig_status_updated',
        'brig_appeal_submitted',
        'permanent_brig_set',
        'permanent_brig_removed',
    ];

    public function mount(): void
    {
        $this->authorize('put-in-brig');
    }

    #[Computed]
    public function entries()
    {
        return ActivityLog::query()
            ->with(['causer', 'subject'])
            ->whereIn('action', self::BRIG_ACTIONS)
            ->latest()
            ->paginate($this->perPage);
    }
}; ?>

<div class="space-y-6 w-full max-w-full">
    <flux:heading size="xl">Brig Activity Log</flux:heading>

    <flux:table :paginate="$this->entries">
        <flux:table.columns>
            <flux:table.column>Date / Time</flux:table.column>
            <flux:table.column>Target User</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>By</flux:table.column>
            <flux:table.column>Details</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($this->entries as $entry)
                <flux:table.row wire:key="brig-log-{{ $entry->id }}">
                    @php
                        $tz = auth()->user()->timezone ?? 'UTC';
                        $localTime = $entry->created_at->setTimezone($tz);
                    @endphp
                    <flux:table.cell class="text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400"
                        title="{{ $localTime->format('Y-m-d H:i:s T') }}">
                        {{ $localTime->format('M j, Y') }}<br>
                        {{ $localTime->format('g:i A') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($entry->subject instanceof User)
                            <flux:link href="{{ route('profile.show', $entry->subject) }}">{{ $entry->subject->name }}</flux:link>
                        @elseif($entry->subject)
                            {{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}
                        @else
                            <span class="text-zinc-500">[deleted]</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:badge size="sm" variant="pill">{{ Str::of($entry->action)->replace('_', ' ')->title() }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($entry->causer)
                            <flux:link href="{{ route('profile.show', $entry->causer) }}">{{ $entry->causer->name }}</flux:link>
                        @else
                            <em class="text-zinc-500">System</em>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-sm text-zinc-400">
                        {{ $entry->description }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
