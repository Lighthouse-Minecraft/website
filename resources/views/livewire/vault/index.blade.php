<?php

use App\Actions\CreateCredential;
use App\Models\Credential;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $website_url = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $totp_secret = '';

    public string $notes = '';

    public string $recovery_codes = '';

    public function mount(): void
    {
        $this->authorize('view-vault');
    }

    #[\Livewire\Attributes\Computed]
    public function credentials()
    {
        $user = auth()->user();

        $withLastAccess = fn ($q) => $q->with([
            'latestAccessLog.user',
        ]);

        if ($user->hasRole('Vault Manager') || $user->isAdmin()) {
            return Credential::orderBy('name')->tap($withLastAccess)->get();
        }

        $positionId = $user->staffPosition?->id;

        if ($positionId === null) {
            return collect();
        }

        return Credential::whereHas('staffPositions', function ($query) use ($positionId) {
            $query->where('staff_positions.id', $positionId);
        })->orderBy('name')->tap($withLastAccess)->get();
    }

    public function openCreate(): void
    {
        $this->authorize('manage-vault');
        $this->reset(['name', 'website_url', 'username', 'email', 'password', 'totp_secret', 'notes', 'recovery_codes']);
        Flux::modal('create-credential-modal')->show();
    }

    public function create(): void
    {
        $this->authorize('manage-vault');

        $this->validate([
            'name' => 'required|string|max:255',
            'website_url' => 'nullable|url|max:500',
            'username' => 'required|string|max:500',
            'email' => 'nullable|email|max:500',
            'password' => 'required|string|max:1000',
            'totp_secret' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:5000',
            'recovery_codes' => 'nullable|string|max:5000',
        ]);

        CreateCredential::run(auth()->user(), [
            'name' => $this->name,
            'website_url' => $this->website_url ?: null,
            'username' => $this->username,
            'email' => $this->email ?: null,
            'password' => $this->password,
            'totp_secret' => $this->totp_secret ?: null,
            'notes' => $this->notes ?: null,
            'recovery_codes' => $this->recovery_codes ?: null,
        ]);

        Flux::modal('create-credential-modal')->close();
        Flux::toast('Credential added to vault.', 'Done', variant: 'success');
        $this->reset(['name', 'website_url', 'username', 'email', 'password', 'totp_secret', 'notes', 'recovery_codes']);
        unset($this->credentials);
    }
}; ?>

<x-layouts.app>
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">Staff Credential Vault</flux:heading>
        @can('manage-vault')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                Add Credential
            </flux:button>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Website</flux:table.column>
            <flux:table.column>Password</flux:table.column>
            <flux:table.column>TOTP</flux:table.column>
            <flux:table.column>Last Accessed</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->credentials as $credential)
                <flux:table.row wire:key="credential-{{ $credential->id }}">
                    <flux:table.cell>
                        <flux:link href="{{ route('vault.detail', $credential) }}" wire:navigate>
                            {{ $credential->name }}
                            @if ($credential->needs_password_change)
                                <flux:badge color="orange" size="sm" class="ml-2">Needs Rotation</flux:badge>
                            @endif
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($credential->website_url)
                            <flux:text variant="subtle" class="text-sm">{{ $credential->website_url }}</flux:text>
                        @else
                            <flux:text variant="subtle" class="text-sm">—</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="zinc" size="sm">••••••••</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($credential->getRawOriginal('totp_secret'))
                            <flux:badge color="blue" size="sm">TOTP</flux:badge>
                        @else
                            <flux:text variant="subtle" class="text-sm">—</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($credential->latestAccessLog)
                            <flux:text variant="subtle" class="text-sm">
                                {{ $credential->latestAccessLog->user?->name ?? '(deleted)' }}
                                <br>
                                <span class="text-xs">{{ $credential->latestAccessLog->created_at->diffForHumans() }}</span>
                            </flux:text>
                        @else
                            <flux:text variant="subtle" class="text-sm">—</flux:text>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text variant="subtle" class="py-4 text-center">
                            No credentials in the vault yet.
                            @can('manage-vault')
                                Use "Add Credential" to get started.
                            @endcan
                        </flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('manage-vault')
        <flux:modal name="create-credential-modal" class="w-full max-w-2xl">
            <div class="space-y-6">
                <flux:heading size="lg">Add Credential</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field class="sm:col-span-2">
                        <flux:label>Name <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="name" placeholder="e.g. Apex Hosting Admin" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Website URL</flux:label>
                        <flux:input wire:model="website_url" type="url" placeholder="https://example.com/login" />
                        <flux:error name="website_url" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Username <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="username" />
                        <flux:error name="username" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model="email" type="email" />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Password <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="password" type="password" />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>TOTP Secret</flux:label>
                        <flux:input wire:model="totp_secret" placeholder="Leave blank if not applicable" />
                        <flux:error name="totp_secret" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Notes</flux:label>
                        <flux:textarea wire:model="notes" rows="3" />
                        <flux:error name="notes" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Recovery Codes</flux:label>
                        <flux:textarea wire:model="recovery_codes" rows="3" placeholder="Paste recovery codes here" />
                        <flux:error name="recovery_codes" />
                    </flux:field>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('create-credential-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="create">Add Credential</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</x-layouts.app>
