<?php

use App\Enums\ThreadStatus;
use App\Models\Thread;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $filter = 'my-open';

    #[Computed]
    public function isStaff(): bool
    {
        $user = auth()->user();

        return $user->can('viewAll', Thread::class) 
            || $user->can('viewDepartment', Thread::class) 
            || $user->can('viewFlagged', Thread::class);
    }

    #[Computed]
    public function tickets()
    {
        $user = auth()->user();
        $query = Thread::with(['createdBy', 'assignedTo', 'participants' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->orderBy('last_message_at', 'desc');

        // Handle filter-specific visibility
        if (in_array($this->filter, ['my-open', 'my-closed'])) {
            // Show tickets where user is a participant
            $query->whereHas('participants', fn($sq) => $sq->where('user_id', $user->id));
            
            // Apply status filter
            if ($this->filter === 'my-open') {
                $query->where('status', '!=', 'closed');
            } else {
                $query->where('status', 'closed');
            }
        } else {
            // Staff-only filters: apply department/permission visibility
            if (! $user->can('viewAll', Thread::class)) {
                $query->where(function ($q) use ($user) {
                    // Department tickets
                    if ($user->can('viewDepartment', Thread::class) && $user->staff_department) {
                        $q->where('department', $user->staff_department);
                    }

                    // Flagged tickets
                    if ($user->can('viewFlagged', Thread::class)) {
                        $q->orWhere('is_flagged', true);
                    }
                });
            }

            // Apply status-based filters
            switch ($this->filter) {
                case 'open':
                    // Show all non-closed tickets (Open, Pending, Resolved)
                    $query->where('status', '!=', 'closed');
                    break;
                case 'closed':
                    $query->where('status', 'closed');
                    break;
                case 'assigned-to-me':
                    $query->where('assigned_to_user_id', $user->id);
                    break;
                case 'unassigned':
                    $query->whereNull('assigned_to_user_id');
                    break;
                case 'flagged':
                    if ($user->can('viewFlagged', Thread::class)) {
                        $query->where('has_open_flags', true);
                    }
                    break;
            }
        }

        return $query->get();
    }



    /**
     * Determines whether the given thread has unread messages for the current authenticated user.
     *
     * Checks the participant record for the authenticated user; if none exists or the participant has no
     * recorded `last_read_at`, the thread is considered unread. Otherwise compares the thread's
     * `last_message_at` to the participant's `last_read_at`.
     *
     * @param Thread $thread The thread to check.
     * @return bool `true` if the thread contains messages the current user has not read, `false` otherwise.
     */
    public function isUnread(Thread $thread): bool
    {
        // Get the participant record for the current user
        $participant = $thread->participants
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$participant || !$participant->last_read_at) {
            return true; // Never read
        }

        return $thread->last_message_at > $participant->last_read_at;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Tickets</flux:heading>
        <div class="flex items-center gap-2">
            @can('createAsStaff', Thread::class)
                <flux:button href="/tickets/create-admin" variant="ghost">Create Admin Ticket</flux:button>
            @endcan
            <flux:button href="/tickets/create" variant="primary">Create Ticket</flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-6 flex items-center gap-2">
        <flux:button 
            wire:click="$set('filter', 'my-open')" 
            variant="{{ $filter === 'my-open' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            My Open Tickets
        </flux:button>
        <flux:button 
            wire:click="$set('filter', 'my-closed')" 
            variant="{{ $filter === 'my-closed' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            My Closed Tickets
        </flux:button>
        @if($this->isStaff)
            <flux:button 
                wire:click="$set('filter', 'open')" 
                variant="{{ $filter === 'open' ? 'primary' : 'ghost' }}"
                size="sm"
            >
                All Open
            </flux:button>
            <flux:button 
                wire:click="$set('filter', 'closed')" 
                variant="{{ $filter === 'closed' ? 'primary' : 'ghost' }}"
                size="sm"
            >
                All Closed
            </flux:button>
            <flux:button 
                wire:click="$set('filter', 'assigned-to-me')" 
                variant="{{ $filter === 'assigned-to-me' ? 'primary' : 'ghost' }}"
                size="sm"
            >
                Assigned to Me
            </flux:button>
            <flux:button 
                wire:click="$set('filter', 'unassigned')" 
                variant="{{ $filter === 'unassigned' ? 'primary' : 'ghost' }}"
                size="sm"
            >
                Unassigned
            </flux:button>
            @can('viewFlagged', Thread::class)
                <flux:button 
                    wire:click="$set('filter', 'flagged')" 
                    variant="{{ $filter === 'flagged' ? 'primary' : 'ghost' }}"
                    size="sm"
                >
                    Flagged
                </flux:button>
            @endcan
        @endif
    </div>

    {{-- Tickets List --}}
    @if($this->tickets->isNotEmpty())
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach($this->tickets as $ticket)
                <a 
                    href="/tickets/{{ $ticket->id }}" 
                    wire:navigate
                    class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm">{{ $ticket->subject }}</flux:heading>
                                <flux:badge size="sm" variant="outline">{{ $ticket->department->label() }}</flux:badge>
                                @if($this->isUnread($ticket))
                                    <flux:badge color="blue" size="sm">New</flux:badge>
                                @endif
                                @if($ticket->has_open_flags)
                                    <flux:badge color="red" size="sm">
                                        <flux:icon.flag class="size-3" />
                                    </flux:badge>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <span>{{ $ticket->subtype->label() }}</span>
                                <span class="mx-2">•</span>
                                <span>Created by {{ $ticket->createdBy->name }}</span>
                                <span class="mx-2">•</span>
                                <span>{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge 
                                color="{{ $ticket->status === \App\Enums\ThreadStatus::Open ? 'green' : ($ticket->status === \App\Enums\ThreadStatus::Pending ? 'amber' : 'zinc') }}"
                            >
                                {{ $ticket->status->label() }}
                            </flux:badge>
                            @if($ticket->assignedTo)
                                <flux:avatar size="sm" :src="null" initials="{{ $ticket->assignedTo->initials() }}" />
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center text-zinc-500 dark:text-zinc-400">
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No Tickets</flux:heading>
            <flux:text class="mt-2">No tickets found matching the current filter.</flux:text>
        </div>
    @endif
</div>