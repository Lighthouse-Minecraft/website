<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $selectedPositionId = null;

    public function mount(): void
    {
        // Default: select the first filled Command Officer position
        $default = StaffPosition::filled()
            ->inDepartment(StaffDepartment::Command)
            ->where('rank', StaffRank::Officer)
            ->ordered()
            ->first();

        // Fallback: first filled position of any department
        if (! $default) {
            $default = StaffPosition::filled()->ordered()->first();
        }

        $this->selectedPositionId = $default?->id;
    }

    public function selectPosition(int $id): void
    {
        $this->selectedPositionId = $id;
    }

    public function getDepartmentsProperty(): array
    {
        $positions = StaffPosition::with(['user.minecraftAccounts', 'user.discordAccounts'])
            ->ordered()
            ->get();

        $grouped = [];
        foreach (StaffDepartment::cases() as $dept) {
            $deptPositions = $positions->where('department', $dept);
            if ($deptPositions->isEmpty()) {
                continue;
            }

            $grouped[] = [
                'department' => $dept,
                'officers' => $deptPositions->where('rank', StaffRank::Officer)->sortBy('title')->values(),
                'crew' => $deptPositions->filter(fn ($p) => in_array($p->rank, [StaffRank::CrewMember, StaffRank::JrCrew]))->sortBy('title')->values(),
            ];
        }

        return $grouped;
    }

    public function getSelectedPositionProperty(): ?StaffPosition
    {
        if (! $this->selectedPositionId) {
            return null;
        }

        return StaffPosition::with(['user.minecraftAccounts', 'user.discordAccounts'])->find($this->selectedPositionId);
    }
}; ?>

<section>
    @include('partials.settings-heading')

    <div class="w-full px-4 py-8 mx-auto sm:px-6 lg:px-8">
        <flux:heading size="2xl" class="mb-8">Our Team</flux:heading>

        <div class="flex flex-col-reverse w-full gap-8 lg:flex-row">
            {{-- Staff Directory --}}
            <div class="space-y-8 lg:w-2/3">
                @foreach($this->departments as $group)
                    <div>
                        <flux:heading size="lg" class="pb-2 mb-4 border-b border-zinc-200 dark:border-zinc-700">
                            {{ $group['department']->label() }} Department
                        </flux:heading>

                        {{-- Officers --}}
                        @if($group['officers']->isNotEmpty())
                            <div class="grid grid-cols-2 gap-4 mb-4 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach($group['officers'] as $pos)
                                    <div
                                        wire:key="staff-{{ $pos->id }}"
                                        wire:click="selectPosition({{ $pos->id }})"
                                        class="p-3 rounded-lg border transition-colors cursor-pointer {{ $selectedPositionId === $pos->id ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-zinc-200 dark:border-zinc-700 hover:border-blue-300 dark:hover:border-blue-600' }}"
                                    >
                                        @if($pos->isFilled())
                                            <div class="flex flex-col items-center gap-2 text-center">
                                                @if($pos->user->staffPhotoUrl())
                                                    <img src="{{ $pos->user->staffPhotoUrl() }}" alt="{{ $pos->user->name }}" class="object-cover w-20 h-20 rounded-lg" />
                                                @elseif($pos->user->avatarUrl())
                                                    <img src="{{ $pos->user->avatarUrl() }}" alt="{{ $pos->user->name }}" class="w-20 h-20 rounded-lg" />
                                                @endif
                                                <div class="min-w-0">
                                                    <flux:link href="{{ route('profile.show', $pos->user) }}" wire:navigate class="block text-sm font-semibold truncate">{{ $pos->user->name }}</flux:link>
                                                    <div class="text-xs truncate text-zinc-500 dark:text-zinc-400">{{ $pos->title }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center gap-2 py-4 text-center">
                                                <div class="font-medium text-zinc-400 dark:text-zinc-500">Open Position</div>
                                                <div class="text-sm text-zinc-400 dark:text-zinc-500">{{ $pos->title }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Crew Members (CrewMember + JrCrew together) --}}
                        @if($group['crew']->isNotEmpty())
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach($group['crew'] as $pos)
                                    <div
                                        wire:key="staff-{{ $pos->id }}"
                                        wire:click="selectPosition({{ $pos->id }})"
                                        class="p-3 rounded-lg border transition-colors cursor-pointer {{ $selectedPositionId === $pos->id ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-zinc-200 dark:border-zinc-700 hover:border-blue-300 dark:hover:border-blue-600' }}"
                                    >
                                        @if($pos->isFilled())
                                            <div class="flex flex-col items-center gap-2 text-center">
                                                @php $isJrCrew = $pos->user->isJrCrew(); @endphp
                                                @if(! $isJrCrew && $pos->user->staffPhotoUrl())
                                                    <img src="{{ $pos->user->staffPhotoUrl() }}" alt="{{ $pos->user->name }}" class="object-cover w-16 h-16 rounded-lg" />
                                                @elseif($pos->user->avatarUrl())
                                                    <img src="{{ $pos->user->avatarUrl() }}" alt="{{ $pos->user->name }}" class="w-16 h-16 rounded-lg" />
                                                @endif
                                                <div class="w-full min-w-0">
                                                    <flux:link href="{{ route('profile.show', $pos->user) }}" wire:navigate class="block text-sm font-medium truncate">{{ $pos->user->name }}</flux:link>
                                                    <div class="text-xs truncate text-zinc-500 dark:text-zinc-400">{{ $pos->title }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center gap-2 py-2 text-center">
                                                <div class="text-sm text-zinc-400 dark:text-zinc-500">Open Position</div>
                                                <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $pos->title }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                @if(empty($this->departments))
                    <flux:text variant="subtle" class="py-12 text-center">No staff positions have been configured yet.</flux:text>
                @endif
            </div>

            {{-- Staff Details Panel --}}
            <div class="lg:w-1/4 lg:sticky lg:top-4 lg:self-start">
                @if($this->selectedPosition)
                    @php $selected = $this->selectedPosition; @endphp
                    <flux:card class="space-y-4">
                        @if($selected->isFilled())
                            @php $isJrCrew = $selected->user->isJrCrew(); @endphp

                            @if(! $isJrCrew && $selected->user->staffPhotoUrl())
                                <img src="{{ $selected->user->staffPhotoUrl() }}" alt="{{ $selected->user->name }}" class="object-cover w-full h-48 rounded-lg" />
                            @elseif($selected->user->avatarUrl())
                                <img src="{{ $selected->user->avatarUrl() }}" alt="{{ $selected->user->name }}" class="w-24 h-24 mx-auto rounded-lg" />
                            @endif

                            @if(! $isJrCrew && $selected->user->staff_first_name)
                                <flux:heading size="lg">{{ $selected->user->staff_first_name }} {{ $selected->user->staff_last_initial }}.</flux:heading>
                            @endif

                            <flux:link href="{{ route('profile.show', $selected->user) }}" wire:navigate class="block text-sm text-zinc-500">{{ $selected->user->name }}</flux:link>
                        @endif

                        <div>
                            <flux:heading size="md">{{ $selected->title }}</flux:heading>
                            <div class="flex gap-2 mt-1">
                                @if($selected->isFilled())
                                    <flux:badge size="sm" color="{{ $selected->user->staff_rank->color() }}">{{ $selected->user->staff_rank->label() }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="{{ $selected->rank->color() }}">{{ $selected->rank->label() }}</flux:badge>
                                @endif
                                <flux:badge size="sm" color="zinc">{{ $selected->department->label() }}</flux:badge>
                            </div>
                        </div>

                        @if($selected->description)
                            <flux:text variant="subtle">{{ $selected->description }}</flux:text>
                        @endif

                        @if($selected->isFilled() && ! $selected->user->isJrCrew() && $selected->user->staff_bio)
                            <div>
                                <flux:heading size="sm" class="mb-1">About</flux:heading>
                                <flux:text>{{ $selected->user->staff_bio }}</flux:text>
                            </div>
                        @endif

                        @if($selected->isVacant())
                            @if($selected->responsibilities)
                                <div>
                                    <flux:heading size="sm" class="mb-1">Responsibilities</flux:heading>
                                    <flux:text>{{ $selected->responsibilities }}</flux:text>
                                </div>
                            @endif
                            @if($selected->requirements)
                                <div>
                                    <flux:heading size="sm" class="mb-1">Requirements</flux:heading>
                                    <flux:text>{{ $selected->requirements }}</flux:text>
                                </div>
                            @endif
                        @endif
                    </flux:card>
                @else
                    <flux:card>
                        <flux:text variant="subtle" class="py-8 text-center">Select a staff member to view their details.</flux:text>
                    </flux:card>
                @endif
            </div>
        </div>
    </div>
</section>
