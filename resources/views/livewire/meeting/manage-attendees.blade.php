<?php

use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public array $selectedAttendees = [];

    public function mount(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    public function getStaffMembersProperty()
    {
        return User::whereIn('staff_rank', [
            StaffRank::JrCrew->value,
            StaffRank::CrewMember->value,
            StaffRank::Officer->value,
        ])
        ->whereNotIn('id', $this->meeting->attendees->pluck('id')->toArray())
        ->orderBy('name')
        ->get();
    }

    public function addAttendees()
    {
        $this->authorize('update', $this->meeting);

        if (empty($this->selectedAttendees)) {
            return;
        }

        $attendeesToAdd = [];
        foreach ($this->selectedAttendees as $userId) {
            $attendeesToAdd[$userId] = ['added_at' => now()];
        }

        $this->meeting->attendees()->attach($attendeesToAdd);

        $this->selectedAttendees = [];
        $this->modal('add-attendees')->close();

        // Refresh the meeting relationship
        $this->meeting->load('attendees');
    }

    public function openModal()
    {
        $this->authorize('update', $this->meeting);
        $this->modal('add-attendees')->show();
    }
}; ?>

<div>
    @if($meeting->status->value === 'in_progress')
        @can('update', $meeting)
            <flux:button wire:click="openModal" variant="primary" color="indigo" size="sm" icon="plus">
                Add Attendee
            </flux:button>
        @endcan

        <flux:modal name="add-attendees" class="min-w-[32rem]">
            <form wire:submit="addAttendees">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">Add Attendees</flux:heading>
                        <flux:text class="mt-2">Select staff members to add to this meeting.</flux:text>
                    </div>

                    @if($this->staffMembers->count() > 0)
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            @foreach($this->staffMembers as $staffMember)
                                <flux:checkbox
                                    wire:model="selectedAttendees"
                                    value="{{ $staffMember->id }}"
                                    label="{{ $staffMember->name }}"
                                    description="{{ $staffMember->staff_rank?->label() }} - {{ $staffMember->staff_title }}"
                                />
                            @endforeach
                        </div>
                    @else
                        <flux:text>All eligible staff members are already attending this meeting.</flux:text>
                    @endif

                    <div class="flex gap-2">
                        <flux:spacer />

                        <flux:modal.close>
                            <flux:button variant="ghost">Cancel</flux:button>
                        </flux:modal.close>

                        @if($this->staffMembers->count() > 0)
                            <flux:button type="submit" variant="primary">Add Selected</flux:button>
                        @endif
                    </div>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
