<?php

use App\Models\ReportCategory;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $newName = '';
    public string $newColor = 'zinc';

    public string $editName = '';
    public string $editColor = 'zinc';
    public ?int $editId = null;

    public array $availableColors = ['red', 'orange', 'yellow', 'green', 'blue', 'indigo', 'purple', 'zinc'];

    public function categories()
    {
        $this->authorize('viewAny', ReportCategory::class);

        return ReportCategory::withCount('disciplineReports')->orderBy('name')->get();
    }

    public function createCategory(): void
    {
        $this->authorize('create', ReportCategory::class);

        $this->validate([
            'newName' => 'required|string|max:255|unique:report_categories,name',
            'newColor' => 'required|string|max:50',
        ]);

        ReportCategory::create([
            'name' => $this->newName,
            'color' => $this->newColor,
        ]);

        Flux::modal('create-category-modal')->close();
        Flux::toast('Report category created.', 'Created', variant: 'success');
        $this->reset(['newName', 'newColor']);
    }

    public function openEditModal(int $id): void
    {
        $category = ReportCategory::findOrFail($id);
        $this->authorize('update', $category);

        $this->editId = $id;
        $this->editName = $category->name;
        $this->editColor = $category->color;
    }

    public function updateCategory(): void
    {
        $category = ReportCategory::findOrFail($this->editId);
        $this->authorize('update', $category);

        $this->validate([
            'editName' => "required|string|max:255|unique:report_categories,name,{$this->editId}",
            'editColor' => 'required|string|max:50',
        ]);

        $category->update([
            'name' => $this->editName,
            'color' => $this->editColor,
        ]);

        Flux::modal('edit-category-modal')->close();
        Flux::toast('Report category updated.', 'Updated', variant: 'success');
        $this->reset(['editName', 'editColor', 'editId']);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Report Categories</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Color</flux:table.column>
            <flux:table.column>Reports</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->categories() as $category)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:badge color="{{ $category->color }}" size="sm">{{ $category->name }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $category->color }}</flux:table.cell>
                    <flux:table.cell>{{ $category->discipline_reports_count }}</flux:table.cell>
                    <flux:table.cell>
                        @can('update', $category)
                            <flux:modal.trigger wire:click="openEditModal({{ $category->id }})" name="edit-category-modal">
                                <flux:button size="sm" icon="pencil-square">Edit</flux:button>
                            </flux:modal.trigger>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:modal.trigger name="create-category-modal">
            <flux:button variant="primary">Create Category</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Create Modal --}}
    <flux:modal name="create-category-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Create Report Category</flux:heading>
        <form wire:submit.prevent="createCategory">
            <div class="space-y-6">
                <flux:input label="Name" wire:model="newName" required placeholder="e.g. Harassment" />
                <flux:select label="Color" wire:model="newColor">
                    @foreach($availableColors as $color)
                        <flux:select.option value="{{ $color }}">{{ ucfirst($color) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal name="edit-category-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Edit Report Category</flux:heading>
        <form wire:submit.prevent="updateCategory">
            <div class="space-y-6">
                <flux:input label="Name" wire:model="editName" required />
                <flux:select label="Color" wire:model="editColor">
                    @foreach($availableColors as $color)
                        <flux:select.option value="{{ $color }}">{{ ucfirst($color) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
