<?php

use Livewire\Volt\Component;

new class extends Component {
    public $tab;

    public function mount()
    {
        $this->tab = 'my-board';
    }
}; ?>

<div class="w-full">
    <livewire:dashboard.alert-in-progress-meeting />

    <flux:tab.group class=" ">
        <div class="text-center flex">
            <flux:tabs variant="segmented" size="xs" wire:model="tab">
                <flux:tab name="my-board">My Board</flux:tab>

                @can('view-ready-room-command')
                    <flux:tab name="command">Command</flux:tab>
                @endcan

                @can('view-ready-room-chaplain')
                    <flux:tab name="chaplain">Chaplain</flux:tab>
                @endcan

                @can('view-ready-room-engineer')
                    <flux:tab name="engineer">Engineer</flux:tab>
                @endcan

                @can('view-ready-room-quartermaster')
                    <flux:tab name="quartermaster">Quartermaster</flux:tab>
                @endcan

                @can('view-ready-room-steward')
                    <flux:tab name="steward">Steward</flux:tab>
                @endcan
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

        @can('view-ready-room-command')
            <flux:tab.panel name="command">
                <livewire:dashboard.ready-room-department department="command" />
            </flux:tab.panel>
        @endcan

        @can('view-ready-room-chaplain')
            <flux:tab.panel name="chaplain">
                <livewire:dashboard.ready-room-department department="chaplain" />
            </flux:tab.panel>
        @endcan

        @can('view-ready-room-engineer')
            <flux:tab.panel name="engineer">
                <livewire:dashboard.ready-room-department department="engineer" />
            </flux:tab.panel>
        @endcan

        @can('view-ready-room-quartermaster')
            <flux:tab.panel name="quartermaster">
                <livewire:dashboard.ready-room-department department="quartermaster" />
            </flux:tab.panel>
        @endcan

        @can('view-ready-room-steward')
            <flux:tab.panel name="steward">
                <livewire:dashboard.ready-room-department department="steward" />
            </flux:tab.panel>
        @endcan
    </flux:tab.group>
</div>
