<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    // Create form
    public string $newTitle = '';
    public string $newDepartment = '';
    public int $newRank = 2;
    public string $newDescription = '';
    public string $newResponsibilities = '';
    public string $newRequirements = '';
    public int $newSortOrder = 0;
    public bool $newAcceptingApplications = false;

    // Edit form
    public string $editTitle = '';
    public string $editDepartment = '';
    public int $editRank = 2;
    public string $editDescription = '';
    public string $editResponsibilities = '';
    public string $editRequirements = '';
    public int $editSortOrder = 0;
    public bool $editAcceptingApplications = false;
    public ?int $editId = null;

    public function getPositionsProperty()
    {
        $this->authorize('viewAny', StaffPosition::class);

        return StaffPosition::with('user')->ordered()->get();
    }

    public function createPosition(): void
    {
        $this->authorize('create', StaffPosition::class);

        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newDepartment' => ['required', 'string', Rule::in(array_column(StaffDepartment::cases(), 'value'))],
            'newRank' => ['required', 'integer', Rule::in([StaffRank::CrewMember->value, StaffRank::Officer->value])],
            'newDescription' => 'nullable|string|max:2000',
            'newResponsibilities' => 'nullable|string|max:2000',
            'newRequirements' => 'nullable|string|max:2000',
            'newSortOrder' => 'required|integer|min:0',
        ]);

        StaffPosition::create([
            'title' => $this->newTitle,
            'department' => $this->newDepartment,
            'rank' => $this->newRank,
            'description' => $this->newDescription ?: null,
            'responsibilities' => $this->newResponsibilities ?: null,
            'requirements' => $this->newRequirements ?: null,
            'sort_order' => $this->newSortOrder,
            'accepting_applications' => $this->newAcceptingApplications,
        ]);

        Flux::modal('create-position-modal')->close();
        Flux::toast('Staff position created.', 'Created', variant: 'success');
        $this->reset(['newTitle', 'newDepartment', 'newRank', 'newDescription', 'newResponsibilities', 'newRequirements', 'newSortOrder', 'newAcceptingApplications']);
    }

    public function openEditModal(int $id): void
    {
        $position = StaffPosition::findOrFail($id);
        $this->authorize('update', $position);

        $this->editId = $id;
        $this->editTitle = $position->title;
        $this->editDepartment = $position->department->value;
        $this->editRank = $position->rank->value;
        $this->editDescription = $position->description ?? '';
        $this->editResponsibilities = $position->responsibilities ?? '';
        $this->editRequirements = $position->requirements ?? '';
        $this->editSortOrder = $position->sort_order;
        $this->editAcceptingApplications = $position->accepting_applications;
    }

    public function updatePosition(): void
    {
        $position = StaffPosition::findOrFail($this->editId);
        $this->authorize('update', $position);

        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editDepartment' => ['required', 'string', Rule::in(array_column(StaffDepartment::cases(), 'value'))],
            'editRank' => ['required', 'integer', Rule::in([StaffRank::CrewMember->value, StaffRank::Officer->value])],
            'editDescription' => 'nullable|string|max:2000',
            'editResponsibilities' => 'nullable|string|max:2000',
            'editRequirements' => 'nullable|string|max:2000',
            'editSortOrder' => 'required|integer|min:0',
        ]);

        $position->update([
            'title' => $this->editTitle,
            'department' => $this->editDepartment,
            'rank' => $this->editRank,
            'description' => $this->editDescription ?: null,
            'responsibilities' => $this->editResponsibilities ?: null,
            'requirements' => $this->editRequirements ?: null,
            'sort_order' => $this->editSortOrder,
            'accepting_applications' => $this->editAcceptingApplications,
        ]);

        Flux::modal('edit-position-modal')->close();
        Flux::toast('Staff position updated.', 'Updated', variant: 'success');
        $this->reset(['editTitle', 'editDepartment', 'editRank', 'editDescription', 'editResponsibilities', 'editRequirements', 'editSortOrder', 'editAcceptingApplications', 'editId']);
    }

    public function deletePosition(int $id): void
    {
        $position = StaffPosition::findOrFail($id);
        $this->authorize('delete', $position);

        if ($position->isFilled()) {
            Flux::toast('Cannot delete a position that is currently assigned.', 'Error', variant: 'danger');
            return;
        }

        $position->delete();
        Flux::toast('Staff position deleted.', 'Deleted', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Staff Positions</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Sort</flux:table.column>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Department</flux:table.column>
            <flux:table.column>Rank</flux:table.column>
            <flux:table.column>Assigned To</flux:table.column>
            <flux:table.column>Apps</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->positions as $position)
                <flux:table.row wire:key="position-row-{{ $position->id }}">
                    <flux:table.cell>{{ $position->sort_order }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $position->title }}</flux:table.cell>
                    <flux:table.cell>{{ $position->department->label() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $position->rank->color() }}">{{ $position->rank->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($position->isFilled())
                            <flux:link href="{{ route('profile.show', $position->user) }}" wire:navigate>{{ $position->user->name }}</flux:link>
                        @else
                            <flux:badge size="sm" color="zinc">Vacant</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($position->accepting_applications)
                            <flux:badge size="sm" color="emerald">Open</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Closed</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:modal.trigger wire:click="openEditModal({{ $position->id }})" name="edit-position-modal">
                                <flux:button size="sm" icon="pencil-square">Edit</flux:button>
                            </flux:modal.trigger>
                            @if($position->isVacant())
                                <flux:button size="sm" icon="trash" variant="ghost" wire:click="deletePosition({{ $position->id }})" wire:confirm="Delete this staff position? This cannot be undone.">Delete</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:modal.trigger name="create-position-modal">
            <flux:button variant="primary">Create Position</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Create Modal --}}
    <flux:modal name="create-position-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Create Staff Position</flux:heading>
        <form wire:submit.prevent="createPosition">
            <div class="space-y-6">
                <flux:input label="Title" wire:model="newTitle" required placeholder="e.g. Head Engineer" />

                <flux:select label="Department" wire:model="newDepartment" required>
                    <flux:select.option value="">Select department...</flux:select.option>
                    @foreach(StaffDepartment::cases() as $dept)
                        <flux:select.option value="{{ $dept->value }}">{{ $dept->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Rank" wire:model="newRank" required>
                    <flux:select.option value="{{ StaffRank::CrewMember->value }}">{{ StaffRank::CrewMember->label() }}</flux:select.option>
                    <flux:select.option value="{{ StaffRank::Officer->value }}">{{ StaffRank::Officer->label() }}</flux:select.option>
                </flux:select>

                <flux:textarea label="Description" wire:model="newDescription" rows="3" placeholder="Short description of what this person does..." />
                <flux:textarea label="Responsibilities" wire:model="newResponsibilities" rows="3" placeholder="What this position is responsible for (shown for open positions)..." />
                <flux:textarea label="Requirements" wire:model="newRequirements" rows="2" placeholder="Special requirements (e.g. minimum age)..." />
                <flux:input label="Sort Order" wire:model="newSortOrder" type="number" min="0" />
                <flux:checkbox wire:model="newAcceptingApplications" label="Accepting Applications" />

                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal name="edit-position-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Edit Staff Position</flux:heading>
        <form wire:submit.prevent="updatePosition">
            <div class="space-y-6">
                <flux:input label="Title" wire:model="editTitle" required />

                <flux:select label="Department" wire:model="editDepartment" required>
                    @foreach(StaffDepartment::cases() as $dept)
                        <flux:select.option value="{{ $dept->value }}">{{ $dept->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Rank" wire:model="editRank" required>
                    <flux:select.option value="{{ StaffRank::CrewMember->value }}">{{ StaffRank::CrewMember->label() }}</flux:select.option>
                    <flux:select.option value="{{ StaffRank::Officer->value }}">{{ StaffRank::Officer->label() }}</flux:select.option>
                </flux:select>

                <flux:textarea label="Description" wire:model="editDescription" rows="3" />
                <flux:textarea label="Responsibilities" wire:model="editResponsibilities" rows="3" />
                <flux:textarea label="Requirements" wire:model="editRequirements" rows="2" />
                <flux:input label="Sort Order" wire:model="editSortOrder" type="number" min="0" />
                <flux:checkbox wire:model="editAcceptingApplications" label="Accepting Applications" />

                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
