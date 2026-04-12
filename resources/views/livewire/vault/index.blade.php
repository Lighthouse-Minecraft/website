<?php

use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        $this->authorize('view-vault');
    }
}; ?>

<x-layouts.app>
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">Staff Credential Vault</flux:heading>
        @can('manage-vault')
            <flux:button variant="primary" icon="plus">
                Add Credential
            </flux:button>
        @endcan
    </div>

    <flux:card>
        <flux:text variant="subtle" class="text-sm">
            No credentials have been added to the vault yet.
        </flux:text>
    </flux:card>
</x-layouts.app>
