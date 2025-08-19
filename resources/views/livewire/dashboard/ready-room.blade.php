<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <flux:tabs variant="segmented">
        @can('view-ready-room-command')
            <flux:tab>Command</flux:tab>
        @endcan

        @can('view-ready-room-chaplain')
            <flux:tab>Chaplain</flux:tab>
        @endcan

        @can('view-ready-room-engineer')
            <flux:tab>Engineer</flux:tab>
        @endcan

        @can('view-ready-room-quartermaster')
            <flux:tab>Quartermaster</flux:tab>
        @endcan

        @can('view-ready-room-steward')
            <flux:tab>Steward</flux:tab>
        @endcan
    </flux:tabs>
</div>
