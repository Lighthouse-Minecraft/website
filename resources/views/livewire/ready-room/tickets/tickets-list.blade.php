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
    public ?string $expandedDepartment = null;

    public function mount(): void
    {
        Gate::authorize('view-ready-room');

        // Expand user's department by default
        if (! $this->expandedDepartment && auth()->user()->staff_department) {
            $this->expandedDepartment = auth()->user()->staff_department->value;
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
        $query = Thread::with(['createdBy', 'assignedTo'])
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
                $query->where('status', ThreadStatus::Open);
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
        $this->expandedDepartment = $this->expandedDepartment === $department ? null : $department;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Tickets</flux:heading>
        <div class="flex items-center gap-2">
            @can('createAsStaff', Thread::class)
                <flux:button href="/ready-room/tickets/create-admin" variant="ghost">Create Admin Ticket</flux:button>
            @endcan
            <flux:button href="/ready-room/tickets/create" variant="primary">Create Ticket</flux:button>
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

    {{-- Tickets by Department --}}
    <div class="space-y-4">
        @forelse($this->accessibleDepartments as $dept)
            @php
                $deptTickets = $this->tickets->get($dept->value, collect());
                $isExpanded = $this->expandedDepartment === $dept->value;
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
                                            href="/ready-room/tickets/{{ $ticket->id }}" 
                                            wire:navigate
                                            class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900 transition"
                                        >
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <flux:heading size="sm">{{ $ticket->subject }}</flux:heading>
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
        @empty
            <div class="text-center py-12">
                <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No accessible departments</flux:heading>
            </div>
        @endforelse
    </div>
</div>
