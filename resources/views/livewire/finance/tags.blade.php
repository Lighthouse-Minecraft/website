<?php

use App\Models\FinancialTag;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    public string $newName = '';

    public string $newColor = 'zinc';

    public ?int $editId = null;

    public string $editName = '';

    public string $editColor = 'zinc';

    public array $allowedColors = [
        'zinc', 'red', 'orange', 'yellow', 'green', 'blue', 'indigo', 'purple', 'pink',
    ];

    public function getTagsProperty()
    {
        return FinancialTag::orderBy('name')->get();
    }

    public function createTag(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'newName' => 'required|string|max:100|unique:financial_tags,name',
            'newColor' => 'required|in:'.implode(',', $this->allowedColors),
        ]);

        FinancialTag::create([
            'name' => $this->newName,
            'color' => $this->newColor,
        ]);

        Flux::modal('create-tag-modal')->close();
        Flux::toast('Tag created.', 'Done', variant: 'success');
        $this->reset(['newName', 'newColor']);
        $this->newColor = 'zinc';
    }

    public function openEdit(int $id): void
    {
        $this->authorize('finance-manage');

        $tag = FinancialTag::findOrFail($id);
        $this->editId = $tag->id;
        $this->editName = $tag->name;
        $this->editColor = $tag->color;

        Flux::modal('edit-tag-modal')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'editName' => 'required|string|max:100|unique:financial_tags,name,'.$this->editId,
            'editColor' => 'required|in:'.implode(',', $this->allowedColors),
        ]);

        FinancialTag::findOrFail($this->editId)->update([
            'name' => $this->editName,
            'color' => $this->editColor,
        ]);

        Flux::modal('edit-tag-modal')->close();
        Flux::toast('Tag updated.', 'Done', variant: 'success');
        $this->reset(['editId', 'editName', 'editColor']);
        $this->editColor = 'zinc';
    }

    public function delete(int $id): void
    {
        $this->authorize('finance-manage');

        $tag = FinancialTag::findOrFail($id);

        // Only allow deletion if no journal entries use this tag
        if ($tag->journalEntries()->exists()) {
            Flux::toast('Cannot delete a tag used on journal entries. Remove it from entries first.', 'Error', variant: 'danger');

            return;
        }

        $tag->delete();
        Flux::toast('Tag deleted.', 'Done', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Tags</flux:heading>
            <flux:text variant="subtle">Manage transaction classification tags.</flux:text>
        </div>
        @can('finance-manage')
            <flux:modal.trigger name="create-tag-modal">
                <flux:button variant="primary" icon="plus">Add Tag</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Tag</flux:table.column>
            <flux:table.column>Color</flux:table.column>
            @can('finance-manage')
                <flux:table.column>Actions</flux:table.column>
            @endcan
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->tags as $tag)
                <flux:table.row wire:key="tag-{{ $tag->id }}">
                    <flux:table.cell>
                        <flux:badge color="{{ $tag->color }}" size="sm">{{ $tag->name }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ ucfirst($tag->color) }}</flux:table.cell>
                    @can('finance-manage')
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="sm" icon="pencil-square" wire:click="openEdit({{ $tag->id }})">Edit</flux:button>
                                <flux:button size="sm" icon="trash" variant="danger" wire:click="delete({{ $tag->id }})">Delete</flux:button>
                            </div>
                        </flux:table.cell>
                    @endcan
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">
                        <flux:text variant="subtle" class="py-4 text-center">No tags yet. Add one to get started.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('finance-manage')
        {{-- Create modal --}}
        <flux:modal name="create-tag-modal" class="w-full max-w-md">
            <div class="space-y-6">
                <flux:heading size="lg">Add Tag</flux:heading>

                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model.live="newName" placeholder="e.g. Donations" />
                    <flux:error name="newName" />
                </flux:field>

                <flux:field>
                    <flux:label>Color</flux:label>
                    <flux:select wire:model.live="newColor">
                        @foreach ($allowedColors as $color)
                            <flux:select.option value="{{ $color }}">{{ ucfirst($color) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="newColor" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('create-tag-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="createTag">Add Tag</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Edit modal --}}
        <flux:modal name="edit-tag-modal" class="w-full max-w-md">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Tag</flux:heading>

                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model.live="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>Color</flux:label>
                    <flux:select wire:model.live="editColor">
                        @foreach ($allowedColors as $color)
                            <flux:select.option value="{{ $color }}">{{ ucfirst($color) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="editColor" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('edit-tag-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="saveEdit">Save</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
