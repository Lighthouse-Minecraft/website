<?php

use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Thread;
use Livewire\Volt\Component;

new class extends Component {
    public string $department;

    #[\Livewire\Attributes\Computed]
    public function unassignedTickets()
    {
        return Thread::where('type', ThreadType::Ticket)
            ->where('department', $this->department)
            ->whereNull('assigned_to_user_id')
            ->whereNotIn('status', [ThreadStatus::Closed])
            ->with(['createdBy', 'assignedTo'])
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function assignedTickets()
    {
        return Thread::where('type', ThreadType::Ticket)
            ->where('department', $this->department)
            ->whereNotNull('assigned_to_user_id')
            ->whereNotIn('status', [ThreadStatus::Closed])
            ->with(['createdBy', 'assignedTo'])
            ->orderBy('last_message_at', 'desc')
            ->get();
    }
}; ?>

<flux:card>
    <flux:heading class="mb-4">{{ ucfirst($department) }} Tickets</flux:heading>

    {{-- Unassigned tickets --}}
    <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Unassigned</flux:text>
    @if($this->unassignedTickets->isEmpty())
        <flux:text variant="subtle" class="text-sm mb-4">No unassigned tickets.</flux:text>
    @else
        <div class="space-y-2 mb-4">
            @foreach($this->unassignedTickets as $ticket)
                <div wire:key="unassigned-ticket-{{ $ticket->id }}" class="flex items-center justify-between p-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="min-w-0 flex-1">
                        <flux:link href="{{ route('tickets.show', $ticket) }}" class="text-sm font-medium truncate block">
                            {{ $ticket->subject }}
                        </flux:link>
                        <flux:text variant="subtle" class="text-xs">
                            by {{ $ticket->createdBy?->name ?? 'Unknown' }}
                        </flux:text>
                    </div>
                    <flux:badge size="sm" color="{{ $ticket->status === ThreadStatus::Pending ? 'amber' : 'blue' }}">
                        {{ ucfirst($ticket->status->value) }}
                    </flux:badge>
                </div>
            @endforeach
        </div>
    @endif

    <flux:separator variant="subtle" class="my-3" />

    {{-- Assigned open tickets --}}
    <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Assigned</flux:text>
    @if($this->assignedTickets->isEmpty())
        <flux:text variant="subtle" class="text-sm">No assigned open tickets.</flux:text>
    @else
        <div class="space-y-2">
            @foreach($this->assignedTickets as $ticket)
                <div wire:key="assigned-ticket-{{ $ticket->id }}" class="flex items-center justify-between p-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="min-w-0 flex-1">
                        <flux:link href="{{ route('tickets.show', $ticket) }}" class="text-sm font-medium truncate block">
                            {{ $ticket->subject }}
                        </flux:link>
                        <flux:text variant="subtle" class="text-xs">
                            by {{ $ticket->createdBy?->name ?? 'Unknown' }} &mdash; assigned to {{ $ticket->assignedTo?->name ?? 'Unknown' }}
                        </flux:text>
                    </div>
                    <flux:badge size="sm" color="{{ $ticket->status === ThreadStatus::Pending ? 'amber' : 'blue' }}">
                        {{ ucfirst($ticket->status->value) }}
                    </flux:badge>
                </div>
            @endforeach
        </div>
    @endif
</flux:card>
