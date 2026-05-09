<?php

use App\Enums\BackgroundCheckStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\BackgroundCheck;
use App\Models\BoardMember;
use App\Models\SiteConfig;
use App\Models\StaffPosition;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $selectedPositionId = null;
    public ?int $selectedBoardMemberId = null;

    public function selectPosition(int $id): void
    {
        $this->selectedPositionId = $id;
        $this->selectedBoardMemberId = null;
        Flux::modal('position-detail-modal')->show();
    }

    public function selectBoardMember(int $id): void
    {
        $this->selectedBoardMemberId = $id;
        $this->selectedPositionId = null;
        Flux::modal('board-member-detail-modal')->show();
    }

    public function bgCheckTooltip(?BackgroundCheck $check): string
    {
        if (! $check) {
            return SiteConfig::getValue('bg_check_no_record_message', 'Waiting for more donations to come in before we can do more background checks');
        }

        return match ($check->status) {
            BackgroundCheckStatus::Passed => 'Background check passed on ' . $check->completed_date->format('M j, Y'),
            BackgroundCheckStatus::Waived => 'A background check is not required for this position',
            default => SiteConfig::getValue('bg_check_no_record_message', 'Waiting for more donations to come in before we can do more background checks'),
        };
    }

    public function bgCheckColor(?BackgroundCheck $check): string
    {
        if (! $check) {
            return 'amber';
        }

        return match ($check->status) {
            BackgroundCheckStatus::Passed => 'green',
            BackgroundCheckStatus::Waived => 'zinc',
            default => 'amber',
        };
    }

    public function getDepartmentsProperty(): array
    {
        $positions = StaffPosition::with(['user.minecraftAccounts', 'user.discordAccounts', 'user.latestTerminalBackgroundCheck'])
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
                'officers' => $deptPositions->where('rank', StaffRank::Officer)->values(),
                'crew' => $deptPositions->filter(fn ($p) => in_array($p->rank, [StaffRank::CrewMember, StaffRank::JrCrew]))->values(),
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

    public function getBoardMembersProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return BoardMember::with(['user.minecraftAccounts', 'user.discordAccounts'])
            ->ordered()
            ->get();
    }

    public function getSelectedBoardMemberProperty(): ?BoardMember
    {
        if (! $this->selectedBoardMemberId) {
            return null;
        }

        return $this->boardMembers->firstWhere('id', $this->selectedBoardMemberId);
    }
}; ?>

<div>
    <div class="w-full px-4 py-8 mx-auto sm:px-6 lg:px-8">
        <flux:heading size="2xl" class="mb-8">Our Team</flux:heading>

        <div class="space-y-8">
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
                                    wire:keydown.enter="selectPosition({{ $pos->id }})"
                                    wire:keydown.space.prevent="selectPosition({{ $pos->id }})"
                                    role="button"
                                    tabindex="0"
                                    class="p-3 rounded-lg border transition-colors cursor-pointer border-zinc-200 dark:border-zinc-700 hover:border-blue-300 dark:hover:border-blue-600"
                                >
                                    @if($pos->isFilled())
                                        @php $bgCheck = $pos->user->latestTerminalBackgroundCheck; @endphp
                                        <div class="flex flex-col items-center gap-2 text-center">
                                            @if($pos->user->staffPhotoUrl())
                                                <img src="{{ $pos->user->staffPhotoUrl() }}" alt="{{ $pos->user->name }}" class="object-cover w-20 h-20 rounded-lg" />
                                            @elseif($pos->user->avatarUrl())
                                                <img src="{{ $pos->user->avatarUrl() }}" alt="{{ $pos->user->name }}" class="w-20 h-20 rounded-lg" />
                                            @endif
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold truncate">{{ $pos->user->name }}</div>
                                                <div class="text-xs truncate text-zinc-500 dark:text-zinc-400">{{ $pos->title }}</div>
                                                <div class="mt-1">
                                                    <flux:badge size="sm" color="{{ $this->bgCheckColor($bgCheck) }}" icon="shield-check" title="{{ $this->bgCheckTooltip($bgCheck) }}">BG Check</flux:badge>
                                                </div>
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
                                    wire:keydown.enter="selectPosition({{ $pos->id }})"
                                    wire:keydown.space.prevent="selectPosition({{ $pos->id }})"
                                    role="button"
                                    tabindex="0"
                                    class="p-3 rounded-lg border transition-colors cursor-pointer border-zinc-200 dark:border-zinc-700 hover:border-blue-300 dark:hover:border-blue-600"
                                >
                                    @if($pos->isFilled())
                                        @php $bgCheck = $pos->user->latestTerminalBackgroundCheck; @endphp
                                        <div class="flex flex-col items-center gap-2 text-center">
                                            @php $isJrCrew = $pos->user->isJrCrew(); @endphp
                                            @if(! $isJrCrew && $pos->user->staffPhotoUrl())
                                                <img src="{{ $pos->user->staffPhotoUrl() }}" alt="{{ $pos->user->name }}" class="object-cover w-16 h-16 rounded-lg" />
                                            @elseif($pos->user->avatarUrl())
                                                <img src="{{ $pos->user->avatarUrl() }}" alt="{{ $pos->user->name }}" class="w-16 h-16 rounded-lg" />
                                            @endif
                                            <div class="w-full min-w-0">
                                                <div class="text-sm font-medium truncate">{{ $pos->user->name }}</div>
                                                <div class="text-xs truncate text-zinc-500 dark:text-zinc-400">{{ $pos->title }}</div>
                                                <div class="mt-1">
                                                    <flux:badge size="sm" color="{{ $this->bgCheckColor($bgCheck) }}" icon="shield-check" title="{{ $this->bgCheckTooltip($bgCheck) }}">BG Check</flux:badge>
                                                </div>
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

            @if(empty($this->departments) && $this->boardMembers->isEmpty())
                <flux:text variant="subtle" class="py-12 text-center">No staff positions have been configured yet.</flux:text>
            @endif

            {{-- Board of Directors --}}
            @if($this->boardMembers->isNotEmpty())
                <div>
                    <flux:heading size="lg" class="pb-2 mb-4 border-b border-zinc-200 dark:border-zinc-700">
                        Board of Directors
                    </flux:heading>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach($this->boardMembers as $member)
                            <div
                                wire:key="board-{{ $member->id }}"
                                wire:click="selectBoardMember({{ $member->id }})"
                                wire:keydown.enter="selectBoardMember({{ $member->id }})"
                                wire:keydown.space.prevent="selectBoardMember({{ $member->id }})"
                                role="button"
                                tabindex="0"
                                class="p-3 rounded-lg border transition-colors cursor-pointer border-zinc-200 dark:border-zinc-700 hover:border-blue-300 dark:hover:border-blue-600"
                            >
                                <div class="flex flex-col items-center gap-2 text-center">
                                    @if($member->effectivePhotoUrl())
                                        <img src="{{ $member->effectivePhotoUrl() }}" alt="{{ $member->effectiveName() }}" class="object-cover w-20 h-20 rounded-lg" />
                                    @endif
                                    <div class="min-w-0">
                                        <div class="block text-sm font-semibold truncate">{{ $member->effectiveName() }}</div>
                                        <div class="text-xs truncate text-zinc-500 dark:text-zinc-400">{{ $member->title ?? 'Board Member' }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Position Detail Modal --}}
    <flux:modal name="position-detail-modal" class="w-full md:max-w-2xl">
        @if($this->selectedPosition)
            @php $selected = $this->selectedPosition; @endphp
            <div class="space-y-4">
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
                        <flux:text>{!! nl2br(e($selected->user->staff_bio)) !!}</flux:text>
                    </div>
                @endif

                @if($selected->responsibilities)
                    <div>
                        <flux:heading size="sm" class="mb-1">Responsibilities</flux:heading>
                        <div class="prose prose-sm dark:prose-invert">
                            {!! Str::markdown($selected->responsibilities, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </div>
                @endif

                @if($selected->isFilled())
                    @can('viewStaffPhone', $selected->user)
                        @if($selected->user->staff_phone)
                            <div>
                                <flux:heading size="sm" class="mb-1">Contact</flux:heading>
                                <flux:text>{{ $selected->user->staff_phone }}</flux:text>
                            </div>
                        @endif
                    @endcan
                @endif

                @if($selected->isVacant())
                    @if($selected->requirements)
                        <div>
                            <flux:heading size="sm" class="mb-1">Requirements</flux:heading>
                            <div class="prose prose-sm dark:prose-invert">
                                {!! Str::markdown($selected->requirements, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        </div>
                    @endif
                @endif

                @auth
                    @if($selected->isAcceptingApplications())
                        @can('create', \App\Models\StaffApplication::class)
                            <div>
                                <flux:button href="{{ route('applications.apply', $selected) }}" variant="primary" icon="paper-airplane" wire:navigate>
                                    Apply for this Position
                                </flux:button>
                            </div>
                        @else
                            <flux:text variant="subtle" class="text-sm italic">You are not eligible to apply at this time.</flux:text>
                        @endcan
                    @else
                        <flux:text variant="subtle" class="text-sm italic">This position is not currently accepting applications.</flux:text>
                    @endif
                @endauth
            </div>
        @endif
    </flux:modal>

    {{-- Board Member Detail Modal --}}
    <flux:modal name="board-member-detail-modal" class="w-full md:max-w-2xl">
        @if($this->selectedBoardMember)
            @php $bm = $this->selectedBoardMember; @endphp
            <div class="space-y-4">
                @if($bm->isLinked() && $bm->user && $bm->user->staffPhotoUrl())
                    <img src="{{ $bm->user->staffPhotoUrl() }}" alt="{{ $bm->effectiveName() }}" class="object-cover w-full h-48 rounded-lg" />
                @elseif($bm->isLinked() && $bm->user && $bm->user->avatarUrl())
                    <img src="{{ $bm->user->avatarUrl() }}" alt="{{ $bm->effectiveName() }}" class="w-24 h-24 mx-auto rounded-lg" />
                @elseif($bm->photo_path)
                    <img src="{{ $bm->effectivePhotoUrl() }}" alt="{{ $bm->effectiveName() }}" class="object-cover w-full h-48 rounded-lg" />
                @endif

                <flux:heading size="lg">{{ $bm->effectiveName() }}</flux:heading>

                @if($bm->isLinked() && $bm->user)
                    <flux:link href="{{ route('profile.show', $bm->user) }}" wire:navigate class="block text-sm text-zinc-500">{{ $bm->user->name }}</flux:link>
                @endif

                <div>
                    <div class="flex gap-2 mt-1">
                        <flux:badge size="sm" color="indigo">{{ $bm->title ?? 'Board Member' }}</flux:badge>
                    </div>
                </div>

                @if($bm->effectiveBio())
                    <div>
                        <flux:heading size="sm" class="mb-1">About</flux:heading>
                        <flux:text>{!! nl2br(e($bm->effectiveBio())) !!}</flux:text>
                    </div>
                @endif

                @if($bm->isLinked() && $bm->user)
                    @can('viewStaffPhone', $bm->user)
                        @if($bm->user->staff_phone)
                            <div>
                                <flux:heading size="sm" class="mb-1">Contact</flux:heading>
                                <flux:text>{{ $bm->user->staff_phone }}</flux:text>
                            </div>
                        @endif
                    @endcan
                @endif
            </div>
        @endif
    </flux:modal>
</div>
