<?php

use App\Actions\DeleteCredential;
use App\Actions\UpdateCredential;
use App\Models\Credential;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public Credential $credential;

    public string $editName = '';

    public string $editWebsiteUrl = '';

    public string $editUsername = '';

    public string $editEmail = '';

    public string $editPassword = '';

    public string $editTotpSecret = '';

    public string $editNotes = '';

    public string $editRecoveryCodes = '';

    public function mount(Credential $credential): void
    {
        $this->authorize('view-vault');
        $this->credential = $credential;
    }

    public function openEdit(): void
    {
        $this->authorize('manage-vault');

        $this->editName = $this->credential->name;
        $this->editWebsiteUrl = $this->credential->website_url ?? '';
        $this->editUsername = $this->credential->username ?? '';
        $this->editEmail = $this->credential->email ?? '';
        $this->editPassword = '';
        $this->editTotpSecret = '';
        $this->editNotes = $this->credential->notes ?? '';
        $this->editRecoveryCodes = $this->credential->recovery_codes ?? '';

        Flux::modal('edit-credential-modal')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('manage-vault');

        $this->validate([
            'editName' => 'required|string|max:255',
            'editWebsiteUrl' => 'nullable|url|max:500',
            'editUsername' => 'required|string|max:500',
            'editEmail' => 'nullable|email|max:500',
            'editPassword' => 'nullable|string|max:1000',
            'editTotpSecret' => 'nullable|string|max:500',
            'editNotes' => 'nullable|string|max:5000',
            'editRecoveryCodes' => 'nullable|string|max:5000',
        ]);

        $data = [
            'name' => $this->editName,
            'website_url' => $this->editWebsiteUrl ?: null,
            'username' => $this->editUsername,
            'email' => $this->editEmail ?: null,
            'totp_secret' => $this->editTotpSecret ?: null,
            'notes' => $this->editNotes ?: null,
            'recovery_codes' => $this->editRecoveryCodes ?: null,
        ];

        if ($this->editPassword !== '') {
            $data['password'] = $this->editPassword;
        }

        $this->credential = UpdateCredential::run($this->credential, auth()->user(), $data);

        Flux::modal('edit-credential-modal')->close();
        Flux::toast('Credential updated.', 'Done', variant: 'success');
    }

    public function confirmDelete(): void
    {
        $this->authorize('manage-vault');
        Flux::modal('delete-confirm-modal')->show();
    }

    public function delete(): void
    {
        $this->authorize('manage-vault');

        DeleteCredential::run($this->credential, auth()->user());

        Flux::modal('delete-confirm-modal')->close();

        $this->redirect(route('vault.index'), navigate: true);
    }
}; ?>

<x-layouts.app>
    <div class="mb-6 flex items-center gap-4">
        <flux:button href="{{ route('vault.index') }}" wire:navigate variant="ghost" icon="arrow-left" size="sm">
            Back to Vault
        </flux:button>
    </div>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">
                    {{ $credential->name }}
                    @if ($credential->needs_password_change)
                        <flux:badge color="orange" class="ml-2">Needs Rotation</flux:badge>
                    @endif
                </flux:heading>
                @if ($credential->website_url)
                    <flux:link href="{{ $credential->website_url }}" target="_blank" class="text-sm">
                        {{ $credential->website_url }}
                    </flux:link>
                @endif
            </div>
            @can('manage-vault')
                <div class="flex gap-2">
                    <flux:button variant="ghost" icon="pencil-square" wire:click="openEdit">Edit</flux:button>
                    <flux:button variant="danger" icon="trash" wire:click="confirmDelete">Delete</flux:button>
                </div>
            @endcan
        </div>

        <flux:card class="space-y-4">
            <flux:heading size="md">Credentials</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:text variant="subtle" class="text-xs uppercase tracking-wide">Username</flux:text>
                    <flux:text class="mt-1 font-mono">{{ $credential->username }}</flux:text>
                </div>

                @if ($credential->email)
                    <div>
                        <flux:text variant="subtle" class="text-xs uppercase tracking-wide">Email</flux:text>
                        <flux:text class="mt-1 font-mono">{{ $credential->email }}</flux:text>
                    </div>
                @endif

                <div>
                    <flux:text variant="subtle" class="text-xs uppercase tracking-wide">Password</flux:text>
                    <flux:text class="mt-1 font-mono text-zinc-400">••••••••••••</flux:text>
                    <flux:text variant="subtle" class="text-xs mt-1">Re-authentication required to reveal</flux:text>
                </div>

                @if ($credential->getRawOriginal('totp_secret'))
                    <div>
                        <flux:text variant="subtle" class="text-xs uppercase tracking-wide">TOTP</flux:text>
                        <flux:badge color="blue" size="sm" class="mt-1">TOTP configured</flux:badge>
                        <flux:text variant="subtle" class="text-xs mt-1">Re-authentication required to view code</flux:text>
                    </div>
                @endif
            </div>
        </flux:card>

        @if ($credential->notes)
            <flux:card class="space-y-2">
                <flux:heading size="md">Notes</flux:heading>
                <flux:text class="whitespace-pre-wrap">{{ $credential->notes }}</flux:text>
            </flux:card>
        @endif

        @if ($credential->getRawOriginal('recovery_codes'))
            <flux:card class="space-y-2">
                <flux:heading size="md">Recovery Codes</flux:heading>
                <flux:text class="whitespace-pre-wrap font-mono">{{ $credential->recovery_codes }}</flux:text>
            </flux:card>
        @endif

        <flux:text variant="subtle" class="text-xs">
            Added by {{ $credential->createdBy->name }} on {{ $credential->created_at->format('M j, Y') }}
            @if ($credential->updatedBy)
                · Last updated by {{ $credential->updatedBy->name }} on {{ $credential->updated_at->format('M j, Y') }}
            @endif
        </flux:text>
    </div>

    @can('manage-vault')
        {{-- Edit modal --}}
        <flux:modal name="edit-credential-modal" class="w-full max-w-2xl">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Credential</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field class="sm:col-span-2">
                        <flux:label>Name <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="editName" />
                        <flux:error name="editName" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Website URL</flux:label>
                        <flux:input wire:model="editWebsiteUrl" type="url" />
                        <flux:error name="editWebsiteUrl" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Username <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="editUsername" />
                        <flux:error name="editUsername" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model="editEmail" type="email" />
                        <flux:error name="editEmail" />
                    </flux:field>

                    <flux:field>
                        <flux:label>New Password</flux:label>
                        <flux:description>Leave blank to keep the current password.</flux:description>
                        <flux:input wire:model="editPassword" type="password" />
                        <flux:error name="editPassword" />
                    </flux:field>

                    <flux:field>
                        <flux:label>TOTP Secret</flux:label>
                        <flux:description>Leave blank to keep the current secret.</flux:description>
                        <flux:input wire:model="editTotpSecret" />
                        <flux:error name="editTotpSecret" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Notes</flux:label>
                        <flux:textarea wire:model="editNotes" rows="3" />
                        <flux:error name="editNotes" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Recovery Codes</flux:label>
                        <flux:textarea wire:model="editRecoveryCodes" rows="3" />
                        <flux:error name="editRecoveryCodes" />
                    </flux:field>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('edit-credential-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="saveEdit">Save Changes</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Delete confirmation modal --}}
        <flux:modal name="delete-confirm-modal" class="w-full max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Delete Credential</flux:heading>
                    <flux:text class="mt-2">
                        Are you sure you want to delete <strong>{{ $credential->name }}</strong>?
                        This cannot be undone. All access logs for this credential will also be removed.
                    </flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('delete-confirm-modal').close()">Cancel</flux:button>
                    <flux:button variant="danger" wire:click="delete">Delete Permanently</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</x-layouts.app>
