<?php

use Livewire\Volt\Component;

new class extends Component {
    public $tab = 'user-manager';
}; ?>

<div class="w-full flex">
    <flux:tab.group>
        <flux:tabs wire:model="tab" variant="pills">
            <flux:tab name="user-manager">User Manager</flux:tab>
            <flux:tab name="page-manager">Page Manager</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="user-manager">
            <div class="p-4">
                <h2 class="text-lg font-semibold">User Management</h2>
                <p>Manage users, roles, and permissions.</p>
            </div>
        </flux:tab.panel>
        <flux:tab.panel name="page-manager">
            <div class="p-4">
                <h2 class="text-lg font-semibold">Page Management</h2>
                <p>Manage pages and the site menu.</p>
            </div>
        </flux:tab.panel>
    </flux:tab.group>


</div>
