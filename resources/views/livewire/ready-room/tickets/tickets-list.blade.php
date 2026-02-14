<?php

use App\Enums\StaffDepartment;
use App\Enums\ThreadStatus;
use App\Models\Thread;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $filter = 'open';

    #[Url]
    public array $expandedDepartments = [];

    public function mount(): void
    {
        // All departments start expanded by default
        if (empty($this->expandedDepartments)) {
            $this->expandedDepartments = array_map(
                fn($dept) => $dept->value,
                $this->accessibleDepartments
            );
        }
    }

    #[Computed]
    public function accessibleDepartments(): array
    {
        $user = auth()->user();
        $departments = [];

        if ($user->can('viewAll', Thread::class)) {
            // Command can see all departments
            return StaffDepartment::cases();
        }

        if ($user->can('viewDepartment', Thread::class) && $user->staff_department) {
            $departments[] = $user->staff_department;
        }

        if ($user->can('viewFlagged', Thread::class)) {
            // Quartermaster can see all departments (for flagged tickets)
            return StaffDepartment::cases();
        }

        return $departments;
    }

    #[Computed]
    public function tickets()
    {
        $user = auth()->user();
        $query = Thread::with(['createdBy', 'assignedTo', 'participants' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->orderBy('last_message_at', 'desc');

        // Apply visibility filters
        if (! $user->can('viewAll', Thread::class)) {
            $query->where(function ($q) use ($user) {
                // User's tickets
                $q->whereHas('participants', fn($sq) => $sq->where('user_id', $user->id));

                // Department tickets
                if ($user->can('viewDepartment', Thread::class) && $user->staff_department) {
                    $q->orWhere('department', $user->staff_department);
                }

                // Flagged tickets
                if ($user->can('viewFlagged', Thread::class)) {
                    $q->orWhere('is_flagged', true);
                }
            });
        }

        // Apply status filter
        switch ($this->filter) {
            case 'open':
                // Show all non-closed tickets (Open, Pending, Resolved)
                $query->where('status', '!=', ThreadStatus::Closed);
                break;
            case 'closed':
                $query->where('status', ThreadStatus::Closed);
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

        return $query->get()->groupBy('department');
    }

    #[Computed]
    public function openTicketCount(): int
    {
        $user = auth()->user();
        $query = Thread::where('status', ThreadStatus::Open);

        if (! $user->can('viewAll', Thread::class)) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('participants', fn($sq) => $sq->where('user_id', $user->id));

                if ($user->can('viewDepartment', Thread::class) && $user->staff_department) {
                    $q->orWhere('department', $user->staff_department);
                }

                if ($user->can('viewFlagged', Thread::class)) {
                    $q->orWhere('is_flagged', true);
                }
            });
        }

        return $query->count();
    }

    #[Computed]
    public function hasPendingTickets(): bool
    {
        $user = auth()->user();
        $query = Thread::where('status', ThreadStatus::Pending);

        if (! $user->can('viewAll', Thread::class)) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('participants', fn($sq) => $sq->where('user_id', $user->id));

                if ($user->can('viewDepartment', Thread::class) && $user->staff_department) {
                    $q->orWhere('department', $user->staff_department);
                }

                if ($user->can('viewFlagged', Thread::class)) {
                    $q->orWhere('is_flagged', true);
                }
            });
        }

        return $query->exists();
    }

    public function toggleDepartment(string $department): void
    {
        if (in_array($department, $this->expandedDepartments)) {
            $this->expandedDepartments = array_values(array_filter(
                $this->expandedDepartments,
                fn($d) => $d !== $department
            ));
        } else {
            $this->expandedDepartments[] = $department;
        }
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
            wire:click="$set('filter', 'open')" 
            variant="{{ $filter === 'open' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Open
        </flux:button>
        <flux:button 
            wire:click="$set('filter', 'closed')" 
            variant="{{ $filter === 'closed' ? 'primary' : 'ghost' }}"
            size="sm"
        >
            Closed
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
    </div>

    {{-- Tickets by Department (Staff View) or Simple List (Regular Users) --}}
    <div class="space-y-4">
        @if($this->accessibleDepartments)
            {{-- Staff view with department grouping --}}
            @foreach($this->accessibleDepartments as $dept)
                @php
                    $deptTickets = $this->tickets->get($dept->value, collect());
                    $isExpanded = in_array($dept->value, $this->expandedDepartments);
                @endphp

                @if($deptTickets->isNotEmpty() || $isExpanded)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <button 
                            wire:click="toggleDepartment('{{ $dept->value }}')"
                            class="w-full flex items-center justify-between p-4 text-left hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                        >
                            <div class="flex items-center gap-3">
                                <flux:heading size="lg">{{ $dept->label() }}</flux:heading>
                                <flux:badge>{{ $deptTickets->count() }}</flux:badge>
                            </div>
                            <flux:icon.chevron-down class="size-5 transition {{ $isExpanded ? 'rotate-180' : '' }}" />
                        </button>

                        @if($isExpanded)
                            <div class="border-t border-zinc-200 dark:border-zinc-700">
                                @if($deptTickets->isNotEmpty())
                                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @foreach($deptTickets as $ticket)
                                            <a 
                                                href="/tickets/{{ $ticket->id }}" 
                                                wire:navigate
                                                class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                                            >
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2">
                                                            <flux:heading size="sm">{{ $ticket->subject }}</flux:heading>
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
                                    <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                                        No tickets found in this department.
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        @else
            {{-- Regular user view - simple list of all their tickets --}}
            @php
                $allTickets = $this->tickets->flatten();
            @endphp

            @if($allTickets->isNotEmpty())
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($allTickets as $ticket)
                        <a 
                            href="/tickets/{{ $ticket->id }}" 
                            wire:navigate
                            class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $ticket->subject }}</flux:heading>
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
                <div class="text-center py-12">
                    <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No Tickets</flux:heading>
                    <flux:text class="mt-2">You don't have any tickets matching the current filter.</flux:text>
                </div>
            @endif
        @endif
    </div>
</div>