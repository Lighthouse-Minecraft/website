<?php

use App\Enums\BrigType;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Thread;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDir = 'asc';

    public ?int $managingUserId = null;

    public function mount(): void
    {
        $this->authorize('put-in-brig');
    }

    #[Computed]
    public function approachingRelease()
    {
        return User::where('in_brig', true)
            ->whereNotNull('brig_expires_at')
            ->where('brig_expires_at', '<=', now()->addDays(7))
            ->where('brig_expires_at', '>', now())
            ->whereNull('permanent_brig_at')
            ->orderBy('brig_expires_at')
            ->get();
    }

    #[Computed]
    public function openAppealsCount(): int
    {
        return Thread::where('type', ThreadType::BrigAppeal)
            ->where('status', ThreadStatus::Open)
            ->count();
    }

    #[Computed]
    public function totalBriggedCount(): int
    {
        return User::where('in_brig', true)->count();
    }

    #[Computed]
    public function allBriggedUsers()
    {
        $query = User::where('in_brig', true);

        if (filled($this->search)) {
            $query->where('name', 'like', '%'.trim($this->search).'%');
        }

        $column = match ($this->sortBy) {
            'brig_type' => 'brig_type',
            'brig_placed_at' => 'brig_placed_at',
            'brig_expires_at' => 'brig_expires_at',
            default => 'name',
        };

        return $query->orderBy($column, $this->sortDir)->get();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function openManageModal(int $userId): void
    {
        $this->authorize('put-in-brig');
        $this->managingUserId = $userId;
    }

    #[Computed]
    public function managingUser(): ?User
    {
        if (! $this->managingUserId) {
            return null;
        }

        return User::find($this->managingUserId);
    }

    private function brigTypeColor(BrigType $type): string
    {
        return match ($type) {
            BrigType::Discipline => 'red',
            BrigType::ParentalPending, BrigType::ParentalDisabled => 'blue',
            BrigType::AgeLock => 'yellow',
            BrigType::RulesNonCompliance => 'orange',
        };
    }
}; ?>

<div>
<flux:card>
    {{-- Header --}}
    <div class="flex items-center gap-3 flex-wrap">
        <flux:heading size="md">Brig Warden</flux:heading>
        <flux:spacer />

        {{-- Open Appeals Badge --}}
        <flux:tooltip content="Open Brig Appeals">
            <flux:link href="{{ route('discussions.index') }}" class="no-underline">
                <flux:badge
                    color="{{ $this->openAppealsCount > 0 ? 'red' : 'zinc' }}"
                    size="sm"
                    icon="chat-bubble-left-ellipsis"
                >
                    {{ $this->openAppealsCount }} {{ Str::plural('Appeal', $this->openAppealsCount) }}
                </flux:badge>
            </flux:link>
        </flux:tooltip>

        {{-- Total Brigged Badge --}}
        <flux:tooltip content="View all brigged users">
            <button type="button" x-on:click="$flux.modal('brig-all-users-modal').show()">
                <flux:badge color="amber" size="sm" icon="lock-closed">
                    {{ $this->totalBriggedCount }} Brigged
                </flux:badge>
            </button>
        </flux:tooltip>

        <flux:button size="xs" x-on:click="$flux.modal('brig-all-users-modal').show()">
            View All
        </flux:button>
    </div>

    <flux:separator variant="subtle" class="my-3" />

    {{-- Approaching Release List --}}
    <flux:text class="font-medium text-sm mb-2">Approaching Release (7 days)</flux:text>

    @if($this->approachingRelease->isEmpty())
        <flux:text variant="subtle" class="text-sm py-2">No users approaching release.</flux:text>
    @else
        <div class="space-y-2">
            @foreach($this->approachingRelease as $brigUser)
                @php $score = $brigUser->disciplineRiskScore(); @endphp
                <div wire:key="approaching-{{ $brigUser->id }}" class="flex items-center gap-2 text-sm flex-wrap">
                    <flux:avatar size="xs" :initials="$brigUser->initials()" />
                    <flux:link href="{{ route('profile.show', $brigUser) }}">
                        {{ $brigUser->name }}
                    </flux:link>
                    <flux:tooltip content="7d: {{ $score['7d'] }} | 30d: {{ $score['30d'] }} | 90d: {{ $score['90d'] }}">
                        <flux:badge color="{{ \App\Models\User::riskScoreColor($score['total']) }}" size="sm">
                            {{ $score['total'] }}
                        </flux:badge>
                    </flux:tooltip>
                    <flux:badge color="zinc" size="sm">
                        {{ $brigUser->brig_expires_at->diffForHumans() }}
                    </flux:badge>
                    <flux:spacer />
                    <flux:button
                        size="xs"
                        wire:click="openManageModal({{ $brigUser->id }})"
                        x-on:click="$flux.modal('brig-manage-user-modal').show()"
                    >
                        Manage
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif
</flux:card>

{{-- Manage Brig Status Modal --}}
<flux:modal name="brig-manage-user-modal" class="w-full md:w-2/3 lg:w-1/2">
    @if($this->managingUser)
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <flux:avatar :initials="$this->managingUser->initials()" />
                <div>
                    <flux:heading size="lg">
                        <flux:link href="{{ route('profile.show', $this->managingUser) }}">{{ $this->managingUser->name }}</flux:link>
                    </flux:heading>
                    <flux:text variant="subtle" class="text-sm">Manage brig status</flux:text>
                </div>
            </div>
            <flux:separator variant="subtle" />
            <livewire:brig.brig-status-manager
                :user="$this->managingUser"
                :key="'manage-'.$this->managingUser->id"
                @brig-status-updated="$flux.modal('brig-manage-user-modal').close()"
            />
        </div>
    @endif
</flux:modal>

{{-- All Brigged Users Modal --}}
<flux:modal name="brig-all-users-modal" class="w-full md:w-3/4 xl:w-2/3">
    <div class="space-y-4">
        <flux:heading size="lg">All Brigged Users ({{ $this->totalBriggedCount }})</flux:heading>

        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name..." icon="magnifying-glass" />

        @if($this->allBriggedUsers->isEmpty())
            <flux:text variant="subtle" class="py-4 text-center">No brigged users found.</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="text-left py-2 pr-3 font-medium">
                                <button type="button" wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100">
                                    Name
                                    @if($sortBy === 'name')
                                        <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-2 pr-3 font-medium">
                                <button type="button" wire:click="sortBy('brig_type')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100">
                                    Type
                                    @if($sortBy === 'brig_type')
                                        <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-2 pr-3 font-medium">Reason</th>
                            <th class="text-left py-2 pr-3 font-medium">
                                <button type="button" wire:click="sortBy('brig_placed_at')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100">
                                    Date Placed
                                    @if($sortBy === 'brig_placed_at')
                                        <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-2 pr-3 font-medium">
                                <button type="button" wire:click="sortBy('brig_expires_at')" class="flex items-center gap-1 hover:text-zinc-900 dark:hover:text-zinc-100">
                                    Expires At
                                    @if($sortBy === 'brig_expires_at')
                                        <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="text-left py-2 pr-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->allBriggedUsers as $brigUser)
                            <tr wire:key="all-brigged-{{ $brigUser->id }}">
                                <td class="py-2 pr-3">
                                    <div class="flex items-center gap-2">
                                        <flux:link href="{{ route('profile.show', $brigUser) }}">{{ $brigUser->name }}</flux:link>
                                        @if($brigUser->permanent_brig_at)
                                            <flux:badge color="zinc" size="sm">Permanent</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 pr-3">
                                    @if($brigUser->brig_type)
                                        <flux:badge
                                            color="{{ $brigUser->brig_type->isParental() || $brigUser->brig_type === \App\Enums\BrigType::AgeLock ? 'blue' : 'red' }}"
                                            size="sm"
                                        >
                                            {{ $brigUser->brig_type->label() }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">Disciplinary</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 max-w-xs">
                                    <flux:text class="truncate block" title="{{ $brigUser->brig_reason }}">
                                        {{ Str::limit($brigUser->brig_reason, 40) }}
                                    </flux:text>
                                </td>
                                <td class="py-2 pr-3 whitespace-nowrap">
                                    <flux:text variant="subtle">
                                        {{ $brigUser->brig_placed_at?->format('M j, Y') ?? '—' }}
                                    </flux:text>
                                </td>
                                <td class="py-2 pr-3 whitespace-nowrap">
                                    @if($brigUser->permanent_brig_at)
                                        <flux:text variant="subtle">Never</flux:text>
                                    @elseif($brigUser->brig_expires_at)
                                        <flux:text variant="subtle">{{ $brigUser->brig_expires_at->format('M j, Y') }}</flux:text>
                                    @else
                                        <flux:text variant="subtle">Indefinite</flux:text>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <flux:button
                                        size="xs"
                                        wire:click="openManageModal({{ $brigUser->id }})"
                                        x-on:click="$flux.modal('brig-all-users-modal').close(); $nextTick(() => $flux.modal('brig-manage-user-modal').show())"
                                    >
                                        Manage
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</flux:modal>
</div>
