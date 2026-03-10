<?php

use App\Models\SiteConfig;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingId = null;
    public string $editValue = '';

    public function getSiteConfigsProperty()
    {
        return SiteConfig::orderBy('key')->get();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('manage-site-config');

        $config = SiteConfig::findOrFail($id);
        $this->editingId = $id;
        $this->editValue = $config->value ?? '';
        Flux::modal('edit-config-modal')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('manage-site-config');

        $config = SiteConfig::findOrFail($this->editingId);
        $config->value = $this->editValue;
        $config->save();

        \Illuminate\Support\Facades\Cache::forget("site_config.{$config->key}");

        $this->editingId = null;
        $this->editValue = '';
        Flux::modal('edit-config-modal')->close();
        Flux::toast("Setting '{$config->key}' updated.", 'Saved', variant: 'success');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editValue = '';
        Flux::modal('edit-config-modal')->close();
    }
}; ?>

<flux:card>
    <flux:heading size="md" class="mb-4">Site Settings</flux:heading>
    <flux:text variant="subtle" class="mb-4">Dynamic configuration values that can be changed without redeploying. Edit a setting by clicking the edit button.</flux:text>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Key</flux:table.column>
            <flux:table.column>Description</flux:table.column>
            <flux:table.column>Value</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->siteConfigs as $config)
                <flux:table.row wire:key="config-{{ $config->id }}">
                    <flux:table.cell class="font-mono text-sm">{{ $config->key }}</flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">{{ $config->description ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-sm max-w-xs truncate">{{ Str::limit($config->value ?? '(empty)', 80) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:click="startEdit({{ $config->id }})" size="sm" icon="pencil-square" variant="ghost" />
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Edit Modal -->
    <flux:modal name="edit-config-modal" class="w-full lg:w-2/3">
        <div class="space-y-6">
            <flux:heading size="lg">Edit Setting</flux:heading>

            @if($editingId)
                @php $editingConfig = \App\Models\SiteConfig::find($editingId); @endphp
                @if($editingConfig)
                    <div>
                        <flux:text class="font-mono font-semibold">{{ $editingConfig->key }}</flux:text>
                        <flux:text variant="subtle" size="sm">{{ $editingConfig->description }}</flux:text>
                    </div>
                @endif
            @endif

            <flux:field>
                <flux:label>Value</flux:label>
                <flux:textarea wire:model="editValue" rows="8" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button wire:click="cancelEdit" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="saveEdit" variant="primary">Save</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>
