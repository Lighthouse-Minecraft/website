<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;

new class extends Component {
    #[Url]
    public $tab = 'user-manager';

    public function reactive()
    {
        return ['tab'];
    }


}; ?>

<div class="w-full flex">
    <flux:tab.group>
        <flux:tabs wire:model="tab" variant="pills">
            @can('viewAny', \App\Models\User::class)
                <flux:tab name="user-manager">User Manager</flux:tab>
            @endcan

            @can('viewAny', \App\Models\Role::class)
                <flux:tab name="role-manager">Role Manager</flux:tab>
            @endcan

            <flux:tab name="page-manager">Page Manager</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="user-manager">
            @can('viewAny', \App\Models\User::class)
                <livewire:admin-manage-users-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="role-manager">
            @can('viewAny', \App\Models\Role::class)
                <livewire:admin-manage-roles-page />
            @endcan
        </flux:tab.panel>

        <flux:tab.panel name="page-manager">
            <div class="p-4">
                <h2 class="text-lg font-semibold">Page Management</h2>
                <p>Manage pages and the site menu.</p>
            </div>
        </flux:tab.panel>
    </flux:tab.group>


</div>
