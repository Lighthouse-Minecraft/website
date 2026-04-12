<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\SiteConfig;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public array $excludedUserIds = [];

    public function mount(Meeting $meeting): void
    {
        $this->meeting = $meeting;
    }

    #[\Livewire\Attributes\Computed]
    public function payoutAmounts(): array
    {
        return [
            StaffRank::JrCrew->value    => (int) SiteConfig::getValue('meeting_payout_jr_crew', '0'),
            StaffRank::CrewMember->value => (int) SiteConfig::getValue('meeting_payout_crew_member', '0'),
            StaffRank::Officer->value    => (int) SiteConfig::getValue('meeting_payout_officer', '0'),
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function allPayoutsDisabled(): bool
    {
        return collect($this->payoutAmounts)->every(fn ($amount) => $amount === 0);
    }

    #[\Livewire\Attributes\Computed]
    public function submittedUserIds(): array
    {
        return MeetingReport::where('meeting_id', $this->meeting->id)
            ->whereNotNull('submitted_at')
            ->pluck('user_id')
            ->toArray();
    }

    #[\Livewire\Attributes\Computed]
    public function attendeesWithEligibility()
    {
        $attendees = $this->meeting->attendees()->orderBy('name')->get();
        $payoutAmounts = $this->payoutAmounts;
        $submittedUserIds = $this->submittedUserIds;

        // Jr Crew don't attend meetings but are still eligible for payouts if
        // they submitted their Staff Update Report. Include them separately.
        $attendeeIds = $attendees->pluck('id');
        $jrCrewSubmitters = User::where('staff_rank', StaffRank::JrCrew->value)
            ->whereNotIn('id', $attendeeIds)
            ->whereIn('id', $submittedUserIds)
            ->orderBy('name')
            ->get();

        $buildRow = function (User $user, bool $attended) use ($payoutAmounts, $submittedUserIds) {
            $rank = $user->staff_rank;
            $formSubmitted = in_array($user->id, $submittedUserIds);
            $mcAccount = $user->primaryMinecraftAccount();
            $amount = ($rank && $rank !== StaffRank::None) ? ($payoutAmounts[$rank->value] ?? 0) : 0;

            $eligible = true;
            $skipReason = null;

            if ($rank === null || $rank === StaffRank::None) {
                $eligible = false;
                $skipReason = 'No staff rank';
            } elseif ($amount === 0) {
                $eligible = false;
                $skipReason = 'Rank payout disabled';
            } elseif (! $formSubmitted) {
                $eligible = false;
                $skipReason = 'Form not submitted';
            } elseif ($rank === StaffRank::Officer && ! $attended) {
                $eligible = false;
                $skipReason = 'Did not attend';
            } elseif ($mcAccount === null) {
                $eligible = false;
                $skipReason = 'No Minecraft account';
            }

            return [
                'user'          => $user,
                'rank'          => $rank,
                'attended'      => $attended,
                'formSubmitted' => $formSubmitted,
                'mcAccount'     => $mcAccount,
                'amount'        => $amount,
                'eligible'      => $eligible,
                'skipReason'    => $skipReason,
                'department'    => $user->staff_department,
            ];
        };

        $rows = $attendees->map(fn ($a) => $buildRow($a, (bool) $a->pivot->attended))
            ->concat($jrCrewSubmitters->map(fn ($a) => $buildRow($a, false)));

        // Sort by rank descending (Officer → Crew Member → Jr Crew), then name.
        return $rows->sortBy([
            fn ($a, $b) => ($b['rank']?->value ?? 0) <=> ($a['rank']?->value ?? 0),
            fn ($a, $b) => $a['user']->name <=> $b['user']->name,
        ])->groupBy(fn ($row) => $row['department']?->value ?? 'none');
    }

    public function toggleExclude(int $userId): void
    {
        $this->authorize('update', $this->meeting);

        if (in_array($userId, $this->excludedUserIds)) {
            $this->excludedUserIds = array_values(
                array_filter($this->excludedUserIds, fn ($id) => $id !== $userId)
            );
        } else {
            $this->excludedUserIds[] = $userId;
        }

        $this->dispatch('payoutExcludedUsersChanged', excludedUserIds: $this->excludedUserIds);
    }
}; ?>

<div>
@if(! $this->allPayoutsDisabled)
    <flux:card class="mt-6">
        <flux:heading class="mb-4">Payout Preview</flux:heading>
        <flux:text variant="subtle" class="mb-4 text-sm">
            Review who will receive Lumen payouts when this meeting is completed.
            Toggle off any staff member to exclude them from this payout.
        </flux:text>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-zinc-200 dark:border-zinc-700">
                        <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400">Name</th>
                        <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400">Rank</th>
                        <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400 text-center">Staff Update Submitted</th>
                        <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400 text-center">Attended</th>
                        <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400 text-center">MC Account</th>
                        <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400 text-right">Amount</th>
                        @can('update', $meeting)
                            <th class="pb-2 font-medium text-zinc-500 dark:text-zinc-400 text-center">Payout Authorized</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->attendeesWithEligibility as $departmentKey => $rows)
                        {{-- Department header row --}}
                        @php
                            $dept = collect(StaffDepartment::cases())->first(fn ($d) => $d->value === $departmentKey);
                        @endphp
                        <tr wire:key="payout-dept-{{ $departmentKey }}">
                            <td colspan="7" class="pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                {{ $dept?->label() ?? 'No Department' }}
                            </td>
                        </tr>
                        @foreach($rows as $row)
                            <tr wire:key="payout-row-{{ $row['user']->id }}"
                                class="{{ ! $row['eligible'] ? 'opacity-50' : '' }}">
                                <td class="py-2 pr-4">
                                    <div>
                                        <flux:link href="{{ route('profile.show', $row['user']) }}">{{ $row['user']->name }}</flux:link>
                                        @if(! $row['eligible'] && $row['skipReason'])
                                            <div class="text-xs text-red-500 dark:text-red-400">{{ $row['skipReason'] }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 pr-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $row['rank']?->label() ?? '—' }}
                                </td>
                                <td class="py-2 pr-4 text-center">
                                    @if($row['formSubmitted'])
                                        <flux:icon name="check-circle" variant="solid" class="w-4 h-4 text-green-500 mx-auto" />
                                    @else
                                        <flux:icon name="x-circle" variant="solid" class="w-4 h-4 text-red-400 mx-auto" />
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-center">
                                    @if($row['rank'] === \App\Enums\StaffRank::Officer)
                                        @if($row['attended'])
                                            <flux:icon name="check-circle" variant="solid" class="w-4 h-4 text-green-500 mx-auto" />
                                        @else
                                            <flux:icon name="x-circle" variant="solid" class="w-4 h-4 text-red-400 mx-auto" />
                                        @endif
                                    @else
                                        <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-center">
                                    @if($row['mcAccount'])
                                        <flux:icon name="check-circle" variant="solid" class="w-4 h-4 text-green-500 mx-auto" />
                                    @else
                                        <flux:icon name="x-circle" variant="solid" class="w-4 h-4 text-red-400 mx-auto" />
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right font-medium">
                                    @if($row['eligible'])
                                        {{ $row['amount'] }} ✦
                                    @else
                                        —
                                    @endif
                                </td>
                                @can('update', $meeting)
                                    <td class="py-2 text-center">
                                        @if($row['eligible'])
                                            <flux:switch
                                                wire:click="toggleExclude({{ $row['user']->id }})"
                                                :checked="! in_array($row['user']->id, $excludedUserIds)"
                                            />
                                        @endif
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-zinc-500 dark:text-zinc-400">
                                No staff members in this meeting.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
@endif
</div>
