<?php

use App\Enums\StaffDepartment;
use App\Models\Meeting;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;

    public function mount(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    #[\Livewire\Attributes\Computed]
    public function attendeesByDepartment()
    {
        return $this->meeting->attendees()
            ->get()
            ->groupBy(fn ($user) => $user->staff_department?->value ?? 'other')
            ->sortKeys()
            ->map(fn ($group) => $group->sortByDesc(fn ($u) => $u->staff_rank->value)->values());
    }

    public function toggleAttendance(int $userId): void
    {
        $this->authorize('update', $this->meeting);

        $current = $this->meeting->attendees()->where('user_id', $userId)->first()?->pivot->attended;
        $this->meeting->attendees()->updateExistingPivot($userId, ['attended' => ! $current]);

        unset($this->attendeesByDepartment);
        $this->dispatch('attendeesUpdated');
    }

    public function markAllPresent(): void
    {
        $this->authorize('update', $this->meeting);

        $this->meeting->attendees()->newPivotQuery()->update(['attended' => true]);

        unset($this->attendeesByDepartment);
        $this->dispatch('attendeesUpdated');
    }

    public function markAllAbsent(): void
    {
        $this->authorize('update', $this->meeting);

        $this->meeting->attendees()->newPivotQuery()->update(['attended' => false]);

        unset($this->attendeesByDepartment);
        $this->dispatch('attendeesUpdated');
    }

    public function openModal(): void
    {
        $this->authorize('update', $this->meeting);
        $this->meeting->load('attendees');
        unset($this->attendeesByDepartment);
        Flux::modal('manage-attendees')->show();
    }
}; ?>

<div>
    @if(in_array($meeting->status->value, ['in_progress', 'finalizing']))
        @can('update', $meeting)
            <flux:button wire:click="openModal" variant="primary" color="indigo" size="sm" icon="user-group">
                Manage Attendees
            </flux:button>
        @endcan

        <flux:modal name="manage-attendees" class="min-w-[36rem]">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Manage Attendees</flux:heading>
                    <div class="flex gap-2">
                        <flux:button wire:click="markAllPresent" size="xs" variant="ghost">All Present</flux:button>
                        <flux:button wire:click="markAllAbsent" size="xs" variant="ghost">All Absent</flux:button>
                    </div>
                </div>

                <div class="space-y-4 max-h-[28rem] overflow-y-auto">
                    @foreach($this->attendeesByDepartment as $department => $members)
                        <div wire:key="dept-{{ $department }}">
                            <flux:text class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                                {{ \App\Enums\StaffDepartment::tryFrom($department)?->label() ?? ucfirst($department) }}
                            </flux:text>
                            <div class="space-y-1">
                                @foreach($members as $member)
                                    <div wire:key="att-{{ $member->id }}" class="flex items-center justify-between p-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        <div class="flex items-center gap-2">
                                            <flux:avatar size="xs" :src="$member->avatarUrl()" />
                                            <div>
                                                <flux:text class="text-sm font-medium">{{ $member->name }}</flux:text>
                                                <flux:text variant="subtle" class="text-xs">{{ $member->staff_rank->label() }}</flux:text>
                                            </div>
                                        </div>
                                        <flux:switch
                                            wire:click="toggleAttendance({{ $member->id }})"
                                            :checked="(bool) $member->pivot->attended"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @if($this->attendeesByDepartment->isEmpty())
                        <flux:text variant="subtle">No staff members have been seeded for this meeting yet.</flux:text>
                    @endif
                </div>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
