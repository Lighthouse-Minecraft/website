<?php

use App\Models\Role;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    /** IDs of currently assigned roles */
    public array $assignedRoleIds = [];

    /** Whether the picker is read-only */
    public bool $readOnly = false;

    #[Computed]
    public function groupedRoles(): array
    {
        $roles = Role::orderBy('name')->get();
        $grouped = [];

        foreach ($roles as $role) {
            $group = $role->group;
            $grouped[$group][] = $role;
        }

        ksort($grouped);

        return $grouped;
    }

    public function toggleRole(int $roleId): void
    {
        if ($this->readOnly) {
            return;
        }

        if (in_array($roleId, $this->assignedRoleIds)) {
            $this->assignedRoleIds = array_values(array_diff($this->assignedRoleIds, [$roleId]));
            $this->dispatch('role-removed', roleId: $roleId);
        } else {
            $this->assignedRoleIds[] = $roleId;
            $this->dispatch('role-added', roleId: $roleId);
        }
    }
}; ?>

<div class="space-y-2">
    @foreach($this->groupedRoles as $group => $roles)
        <div x-data="{ open: false }" class="border border-zinc-200 dark:border-zinc-700 rounded-lg">
            <button
                type="button"
                x-on:click="open = !open"
                class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg"
            >
                <span class="flex items-center gap-2">
                    {{ $group }}
                    <span class="text-xs text-zinc-400">
                        ({{ collect($roles)->whereIn('id', $assignedRoleIds)->count() }}/{{ count($roles) }})
                    </span>
                </span>
                <flux:icon x-bind:class="open ? 'rotate-180' : ''" name="chevron-down" class="w-4 h-4 transition-transform" />
            </button>
            <div x-show="open" x-collapse class="px-3 pb-3">
                <div class="flex flex-wrap gap-2">
                    @foreach($roles as $role)
                        @if($readOnly)
                            <flux:badge
                                wire:key="role-badge-{{ $role->id }}"
                                size="sm"
                                color="{{ in_array($role->id, $assignedRoleIds) ? $role->color : 'zinc' }}"
                                icon="{{ $role->icon }}"
                                class="{{ in_array($role->id, $assignedRoleIds) ? '' : 'opacity-40' }}"
                            >
                                {{ $role->name }}
                            </flux:badge>
                        @else
                            <button
                                type="button"
                                wire:key="role-toggle-{{ $role->id }}"
                                wire:click="toggleRole({{ $role->id }})"
                                class="inline-flex"
                            >
                                <flux:badge
                                    size="sm"
                                    color="{{ in_array($role->id, $assignedRoleIds) ? $role->color : 'zinc' }}"
                                    icon="{{ in_array($role->id, $assignedRoleIds) ? 'check-circle' : $role->icon }}"
                                    class="cursor-pointer hover:opacity-80 {{ in_array($role->id, $assignedRoleIds) ? '' : 'opacity-40' }}"
                                >
                                    {{ $role->name }}
                                </flux:badge>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
