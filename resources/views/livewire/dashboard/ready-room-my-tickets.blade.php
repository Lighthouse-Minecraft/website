<?php

use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Thread;
use Livewire\Volt\Component;

new class extends Component {
    #[\Livewire\Attributes\Computed]
    public function myOpenTickets()
    {
        return Thread::where('type', ThreadType::Ticket)
            ->where('assigned_to_user_id', auth()->id())
            ->whereNotIn('status', [ThreadStatus::Closed])
            ->with('createdBy')
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function unassignedDepartmentTickets()
    {
        $department = auth()->user()->staff_department;

        if (! $department) {
            return collect();
        }

        return Thread::where('type', ThreadType::Ticket)
            ->where('department', $department)
            ->whereNull('assigned_to_user_id')
            ->whereNotIn('status', [ThreadStatus::Closed])
            ->with('createdBy')
            ->orderBy('last_message_at', 'desc')
            ->get();
    }
}; ?>

<flux:card>
    <flux:heading class="mb-4">Tickets</flux:heading>

    {{-- My assigned open tickets --}}
    <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Assigned to Me</flux:text>
    @if($this->myOpenTickets->isEmpty())
        <flux:text variant="subtle" class="text-sm mb-4">No open tickets assigned to you.</flux:text>
    @else
        <div class="space-y-2 mb-4">
            @foreach($this->myOpenTickets as $ticket)
                <div wire:key="my-ticket-{{ $ticket->id }}" class="flex items-center justify-between p-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
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

    {{-- Unassigned department tickets --}}
    <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
        Unassigned {{ auth()->user()->staff_department?->label() }} Tickets
    </flux:text>
    @if($this->unassignedDepartmentTickets->isEmpty())
        <flux:text variant="subtle" class="text-sm">No unassigned tickets in your department.</flux:text>
    @else
        <div class="space-y-2">
            @foreach($this->unassignedDepartmentTickets as $ticket)
                <div wire:key="dept-ticket-{{ $ticket->id }}" class="flex items-center justify-between p-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
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
</flux:card>
