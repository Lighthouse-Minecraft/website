<?php

use App\Enums\StaffDepartment;
use Livewire\Volt\Component;

new class extends Component {
    public $tab;
    public string $selectedDepartment = '';

    public function mount()
    {
        $this->tab = 'my-board';
        $this->selectedDepartment = auth()->user()->staff_department?->value ?? StaffDepartment::Command->value;
    }

    #[\Livewire\Attributes\Computed]
    public function availableDepartments()
    {
        $user = auth()->user();
        $departments = [];

        foreach (StaffDepartment::cases() as $dept) {
            if ($user->can("view-ready-room-{$dept->value}")) {
                $departments[] = $dept;
            }
        }

        return $departments;
    }
}; ?>

<div class="w-full">
    <livewire:dashboard.alert-in-progress-meeting />

    <flux:tab.group class=" ">
        <div class="text-center flex">
            <flux:tabs variant="segmented" size="xs" wire:model="tab">
                <flux:tab name="my-board">My Board</flux:tab>
                <flux:tab name="department">Department Board</flux:tab>
                <flux:tab name="meeting-minutes">Meeting Minutes</flux:tab>
            </flux:tabs>
        </div>


        <flux:tab.panel name="my-board">
            {{-- Row 1: Upcoming Meetings | Engagement Stats | Recent Reports --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <livewire:dashboard.ready-room-upcoming-meetings />
                <livewire:dashboard.ready-room-my-engagement />
                <livewire:dashboard.ready-room-my-reports />
            </div>

            {{-- Row 2: Tasks | Tickets --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <livewire:dashboard.ready-room-my-tasks />
                <livewire:dashboard.ready-room-my-tickets />
            </div>

            {{-- Row 3: Department Meeting Notes --}}
            <livewire:dashboard.ready-room-my-department-notes />
        </flux:tab.panel>

        <flux:tab.panel name="department">
            @if(count($this->availableDepartments) > 0)
                <div class="mb-6">
                    <flux:select wire:model.live="selectedDepartment" class="w-64">
                        @foreach($this->availableDepartments as $dept)
                            <flux:select.option value="{{ $dept->value }}">{{ $dept->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <livewire:dashboard.ready-room-department :department="$selectedDepartment" :key="'dept-' . $selectedDepartment" />
            @else
                <flux:card>
                    <flux:text variant="subtle" class="text-sm">You do not have access to any department boards.</flux:text>
                </flux:card>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="meeting-minutes">
            <livewire:dashboard.ready-room-meeting-minutes />
        </flux:tab.panel>
    </flux:tab.group>
</div>
