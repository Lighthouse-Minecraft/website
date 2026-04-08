<?php

use App\Models\FinancialVendor;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    public string $newName = '';

    public ?int $editId = null;

    public string $editName = '';

    public function getVendorsProperty()
    {
        return FinancialVendor::orderByDesc('is_active')->orderBy('name')->get();
    }

    public function createVendor(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'newName' => 'required|string|max:255|unique:financial_vendors,name',
        ]);

        FinancialVendor::create([
            'name' => $this->newName,
            'is_active' => true,
        ]);

        Flux::modal('create-vendor-modal')->close();
        Flux::toast('Vendor created.', 'Done', variant: 'success');
        $this->reset('newName');
    }

    public function openEdit(int $id): void
    {
        $this->authorize('finance-manage');

        $vendor = FinancialVendor::findOrFail($id);
        $this->editId = $vendor->id;
        $this->editName = $vendor->name;

        Flux::modal('edit-vendor-modal')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'editName' => 'required|string|max:255|unique:financial_vendors,name,'.$this->editId,
        ]);

        FinancialVendor::findOrFail($this->editId)->update(['name' => $this->editName]);

        Flux::modal('edit-vendor-modal')->close();
        Flux::toast('Vendor updated.', 'Done', variant: 'success');
        $this->reset(['editId', 'editName']);
    }

    public function deactivate(int $id): void
    {
        $this->authorize('finance-manage');

        $vendor = FinancialVendor::findOrFail($id);
        $vendor->update(['is_active' => false]);

        Flux::toast("{$vendor->name} deactivated.", 'Done', variant: 'success');
    }

    public function reactivate(int $id): void
    {
        $this->authorize('finance-manage');

        $vendor = FinancialVendor::findOrFail($id);
        $vendor->update(['is_active' => true]);

        Flux::toast("{$vendor->name} reactivated.", 'Done', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    @include('livewire.finance.partials.nav')

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Vendors</flux:heading>
            <flux:text variant="subtle">Manage payee organizations used on expense entries.</flux:text>
        </div>
        @can('finance-manage')
            <flux:modal.trigger name="create-vendor-modal">
                <flux:button variant="primary" icon="plus">Add Vendor</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            @can('finance-manage')
                <flux:table.column>Actions</flux:table.column>
            @endcan
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->vendors as $vendor)
                <flux:table.row wire:key="vendor-{{ $vendor->id }}">
                    <flux:table.cell>
                        @if (!$vendor->is_active)
                            <span class="line-through text-zinc-400">{{ $vendor->name }}</span>
                        @else
                            {{ $vendor->name }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($vendor->is_active)
                            <flux:badge color="green" size="sm">Active</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Inactive</flux:badge>
                        @endif
                    </flux:table.cell>
                    @can('finance-manage')
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="sm" icon="pencil-square" wire:click="openEdit({{ $vendor->id }})">Edit</flux:button>
                                @if ($vendor->is_active)
                                    <flux:button size="sm" icon="archive-box" variant="danger" wire:click="deactivate({{ $vendor->id }})">Deactivate</flux:button>
                                @else
                                    <flux:button size="sm" icon="arrow-path" wire:click="reactivate({{ $vendor->id }})">Reactivate</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    @endcan
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">
                        <flux:text variant="subtle" class="py-4 text-center">No vendors yet. Add one to get started.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('finance-manage')
        {{-- Create modal --}}
        <flux:modal name="create-vendor-modal" class="w-full max-w-md">
            <div class="space-y-6">
                <flux:heading size="lg">Add Vendor</flux:heading>

                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model.live="newName" placeholder="e.g. Apex Hosting" />
                    <flux:error name="newName" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('create-vendor-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="createVendor">Add Vendor</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Edit modal --}}
        <flux:modal name="edit-vendor-modal" class="w-full max-w-md">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Vendor</flux:heading>

                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model.live="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('edit-vendor-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="saveEdit">Save</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
