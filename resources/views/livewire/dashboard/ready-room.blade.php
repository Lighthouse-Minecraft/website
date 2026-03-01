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
            <div class="w-full block md:flex gap-6">
                <div class="w-full md:w-1/3 lg:w-1/4 mb-6 md:mb-0">
                    <livewire:dashboard.ready-room-upcoming-meetings />
                </div>
                <div class="w-full md:w-2/3 lg:w-3/4">
                    <livewire:dashboard.ready-room-my-tasks />
                </div>
            </div>
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
