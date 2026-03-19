<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Role;
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

    // Role management
    public ?int $rolePositionId = null;
    public ?int $selectedRoleId = null;

    public function getPositionsProperty()
    {
        $this->authorize('viewAny', StaffPosition::class);

        return StaffPosition::with(['user', 'roles'])->ordered()->get();
    }

    public function getAllRolesProperty()
    {
        return Role::orderBy('name')->get();
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

    public function openRolesModal(int $positionId): void
    {
        $position = StaffPosition::findOrFail($positionId);
        $this->authorize('update', $position);

        $this->rolePositionId = $positionId;
        $this->selectedRoleId = null;
        Flux::modal('manage-roles-modal')->show();
    }

    public function addRoleToPosition(): void
    {
        if (! $this->rolePositionId || ! $this->selectedRoleId) {
            return;
        }

        $position = StaffPosition::findOrFail($this->rolePositionId);
        $this->authorize('update', $position);

        $position->roles()->syncWithoutDetaching([$this->selectedRoleId]);
        $this->selectedRoleId = null;

        Flux::toast('Role assigned to position.', 'Added', variant: 'success');
    }

    public function removeRoleFromPosition(int $roleId): void
    {
        if (! $this->rolePositionId) {
            return;
        }

        $position = StaffPosition::findOrFail($this->rolePositionId);
        $this->authorize('update', $position);

        $position->roles()->detach($roleId);

        Flux::toast('Role removed from position.', 'Removed', variant: 'success');
    }

    public function toggleAllowAll(int $positionId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only admins can toggle Allow All.');
        $position = StaffPosition::findOrFail($positionId);

        if ($position->has_all_roles_at) {
            $position->has_all_roles_at = null;
            $position->save();
            Flux::toast('Allow All disabled for this position.', 'Updated', variant: 'success');
        } else {
            $position->has_all_roles_at = now();
            $position->save();
            Flux::toast('Allow All enabled for this position.', 'Updated', variant: 'success');
        }
    }

    public function getRolePositionProperty(): ?StaffPosition
    {
        if (! $this->rolePositionId) {
            return null;
        }

        return StaffPosition::with('roles')->find($this->rolePositionId);
    }

    public function getUnassignedRolesProperty()
    {
        $assignedRoleIds = StaffPosition::whereNull('has_all_roles_at')
            ->with('roles')
            ->get()
            ->flatMap(fn ($p) => $p->roles->pluck('id'))
            ->unique();

        return Role::whereNotIn('id', $assignedRoleIds)->orderBy('name')->get();
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
            <flux:table.column>Roles</flux:table.column>
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
                        @if($position->has_all_roles_at)
                            <flux:badge size="sm" color="amber" icon="star">Allow All</flux:badge>
                        @elseif($position->roles->isNotEmpty())
                            <div class="flex flex-wrap gap-1">
                                @foreach($position->roles as $role)
                                    <flux:badge size="sm" color="{{ $role->color }}" icon="{{ $role->icon }}">{{ $role->name }}</flux:badge>
                                @endforeach
                            </div>
                        @else
                            <flux:text variant="subtle" class="text-sm">None</flux:text>
                        @endif
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
                            <flux:button size="sm" icon="shield-check" wire:click="openRolesModal({{ $position->id }})">Roles</flux:button>
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

    <div class="w-full flex justify-end gap-2">
        <flux:modal.trigger name="check-role-usage-modal">
            <flux:button variant="ghost" icon="clipboard-document-check">Check Role Usage</flux:button>
        </flux:modal.trigger>
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

    {{-- Check Role Usage Modal --}}
    <flux:modal name="check-role-usage-modal" class="w-full lg:w-1/2 space-y-6">
        <flux:heading size="lg">Role Usage Check</flux:heading>

        @if($this->unassignedRoles->isEmpty())
            <div class="flex items-center gap-2 p-3 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/20">
                <flux:icon name="check-circle" variant="solid" class="text-emerald-500" />
                <flux:text>All roles are assigned to at least one position.</flux:text>
            </div>
        @else
            <flux:text variant="subtle">The following roles have not been assigned to any staff position:</flux:text>
            <div class="flex flex-wrap gap-2">
                @foreach($this->unassignedRoles as $role)
                    <flux:badge size="sm" color="{{ $role->color }}" icon="{{ $role->icon }}">{{ $role->name }}</flux:badge>
                @endforeach
            </div>
            <flux:text variant="subtle" class="text-sm">
                {{ $this->unassignedRoles->count() }} of {{ $this->allRoles->count() }} roles are unassigned.
            </flux:text>
        @endif

        <div class="flex justify-end">
            <flux:button variant="ghost" x-on:click="$flux.modal('check-role-usage-modal').close()">Close</flux:button>
        </div>
    </flux:modal>

    {{-- Manage Roles Modal --}}
    <flux:modal name="manage-roles-modal" class="w-full lg:w-1/2 space-y-6">
        @if($this->rolePosition)
            <flux:heading size="lg">Manage Roles: {{ $this->rolePosition->title }}</flux:heading>

            {{-- Allow All Toggle (Admin only) --}}
            @if(auth()->user()->isAdmin())
                <div class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div>
                        <flux:heading size="sm">Allow All Roles</flux:heading>
                        <flux:text variant="subtle" class="text-sm">Grant all roles to this position without assigning them individually.</flux:text>
                    </div>
                    <flux:button
                        wire:click="toggleAllowAll({{ $this->rolePosition->id }})"
                        size="sm"
                        variant="{{ $this->rolePosition->has_all_roles_at ? 'danger' : 'primary' }}"
                    >
                        {{ $this->rolePosition->has_all_roles_at ? 'Disable' : 'Enable' }}
                    </flux:button>
                </div>
            @endif

            @if($this->rolePosition->has_all_roles_at)
                <flux:text variant="subtle">This position has Allow All enabled. Individual role assignments are not needed.</flux:text>
            @else
                {{-- Current Roles --}}
                <div>
                    <flux:heading size="sm" class="mb-2">Assigned Roles</flux:heading>
                    @if($this->rolePosition->roles->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($this->rolePosition->roles as $role)
                                <div wire:key="assigned-role-{{ $role->id }}" class="flex items-center gap-1">
                                    <flux:badge size="sm" color="{{ $role->color }}" icon="{{ $role->icon }}">{{ $role->name }}</flux:badge>
                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeRoleFromPosition({{ $role->id }})" class="hover:!text-red-600 dark:hover:!text-red-400" />
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text variant="subtle" class="text-sm">No roles assigned yet.</flux:text>
                    @endif
                </div>

                {{-- Add Role --}}
                <div>
                    <flux:heading size="sm" class="mb-2">Add Role</flux:heading>
                    <div class="flex gap-2">
                        <flux:select wire:model="selectedRoleId" class="flex-1">
                            <flux:select.option value="">Select a role...</flux:select.option>
                            @foreach($this->allRoles as $role)
                                @if(! $this->rolePosition->roles->contains('id', $role->id))
                                    <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                                @endif
                            @endforeach
                        </flux:select>
                        <flux:button wire:click="addRoleToPosition" variant="primary" size="sm" icon="plus">Add</flux:button>
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('manage-roles-modal').close()">Done</flux:button>
            </div>
        @endif
    </flux:modal>
</div>
