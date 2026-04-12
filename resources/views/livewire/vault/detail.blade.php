<?php

use App\Actions\AssignCredentialPositions;
use App\Actions\DeleteCredential;
use App\Actions\GenerateTotpCode;
use App\Actions\ReauthenticateVaultSession;
use App\Actions\RecordCredentialAccess;
use App\Actions\UpdateCredential;
use App\Models\Credential;
use App\Models\StaffPosition;
use App\Services\VaultSession;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component
{
    public Credential $credential;

    // Edit fields (Vault Manager — all fields)
    public string $editName = '';

    public string $editWebsiteUrl = '';

    public string $editUsername = '';

    public string $editEmail = '';

    public string $editPassword = '';

    public string $editTotpSecret = '';

    public string $editNotes = '';

    public string $editRecoveryCodes = '';

    // Position assignment
    public ?int $addPositionId = null;

    // Password reveal
    public ?string $revealedPassword = null;

    // TOTP display
    public ?string $totpCode = null;

    public int $totpSecondsRemaining = 0;

    // Re-auth modal
    public string $reauthPassword = '';

    public string $reauthError = '';

    // 'password' | 'totp' — what to do after successful re-auth
    public string $reauthPurpose = 'password';

    public function mount(Credential $credential): void
    {
        $this->authorize('view', $credential);
        $this->credential = $credential;
    }

    #[\Livewire\Attributes\Computed]
    public function isSessionUnlocked(): bool
    {
        return app(VaultSession::class)->isUnlocked();
    }

    public function revealPassword(): void
    {
        $this->authorize('view', $this->credential);

        if (app(VaultSession::class)->isUnlocked()) {
            $this->revealedPassword = $this->credential->password;
            RecordCredentialAccess::run($this->credential, auth()->user(), 'viewed_password');
        } else {
            $this->reauthPurpose = 'password';
            $this->reauthPassword = '';
            $this->reauthError = '';
            Flux::modal('reauth-modal')->show();
        }
    }

    public function showTotp(): void
    {
        $this->authorize('view', $this->credential);

        // TOTP always requires re-auth, even if vault session is unlocked
        $this->reauthPurpose = 'totp';
        $this->reauthPassword = '';
        $this->reauthError = '';
        Flux::modal('reauth-modal')->show();
    }

    public function refreshTotp(): void
    {
        $this->authorize('view', $this->credential);

        // Auto-refresh: does not require re-auth, just fetches next code
        if ($this->totpCode === null) {
            return;
        }

        $result = GenerateTotpCode::run($this->credential);
        $this->totpCode = $result['code'];
        $this->totpSecondsRemaining = $result['seconds_remaining'];
    }

    public function closeTotp(): void
    {
        $this->totpCode = null;
        $this->totpSecondsRemaining = 0;
        Flux::modal('totp-modal')->close();
    }

    public function reauth(): void
    {
        $this->authorize('view', $this->credential);

        $this->validate(['reauthPassword' => 'required|string']);

        $user = auth()->user();

        if ($this->reauthPurpose === 'totp') {
            // TOTP re-auth verifies identity only — does not unlock the vault session
            if (! Hash::check($this->reauthPassword, $user->password)) {
                $this->reauthError = 'Incorrect password. Please try again.';

                return;
            }
        } else {
            // Password reveal unlocks the vault session for the configured TTL
            $success = ReauthenticateVaultSession::run($user, $this->reauthPassword);

            if (! $success) {
                $this->reauthError = 'Incorrect password. Please try again.';

                return;
            }
        }

        $this->reauthPassword = '';
        $this->reauthError = '';

        Flux::modal('reauth-modal')->close();

        if ($this->reauthPurpose === 'totp') {
            $result = GenerateTotpCode::run($this->credential);
            $this->totpCode = $result['code'];
            $this->totpSecondsRemaining = $result['seconds_remaining'];
            RecordCredentialAccess::run($this->credential, $user, 'viewed_totp');
            Flux::modal('totp-modal')->show();
        } else {
            Flux::toast('Vault session unlocked.', 'Done', variant: 'success');
            $this->revealedPassword = $this->credential->password;
            RecordCredentialAccess::run($this->credential, $user, 'viewed_password');
        }
    }

    #[\Livewire\Attributes\Computed]
    public function isVaultManager(): bool
    {
        return auth()->user()->hasRole('Vault Manager') || auth()->user()->isAdmin();
    }

    #[\Livewire\Attributes\Computed]
    public function assignedPositions()
    {
        return $this->credential->staffPositions()->with('user')->ordered()->get();
    }

    #[\Livewire\Attributes\Computed]
    public function availablePositions()
    {
        $assignedIds = $this->assignedPositions->pluck('id');

        return StaffPosition::ordered()->get()->filter(fn ($p) => ! $assignedIds->contains($p->id));
    }

    public function openEdit(): void
    {
        $this->authorize('update', $this->credential);

        $this->editUsername = $this->credential->username ?? '';
        $this->editEmail = $this->credential->email ?? '';
        $this->editPassword = '';
        $this->editTotpSecret = '';
        $this->editNotes = $this->credential->notes ?? '';
        $this->editRecoveryCodes = $this->credential->recovery_codes ?? '';

        if ($this->isVaultManager) {
            $this->editName = $this->credential->name;
            $this->editWebsiteUrl = $this->credential->website_url ?? '';
        }

        Flux::modal('edit-credential-modal')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('update', $this->credential);

        if ($this->isVaultManager) {
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
                'notes' => $this->editNotes ?: null,
                'recovery_codes' => $this->editRecoveryCodes ?: null,
            ];

            if ($this->editTotpSecret !== '') {
                $data['totp_secret'] = $this->editTotpSecret;
            }
        } else {
            // Position holders cannot edit name or website URL
            $this->validate([
                'editUsername' => 'required|string|max:500',
                'editEmail' => 'nullable|email|max:500',
                'editPassword' => 'nullable|string|max:1000',
                'editTotpSecret' => 'nullable|string|max:500',
                'editNotes' => 'nullable|string|max:5000',
                'editRecoveryCodes' => 'nullable|string|max:5000',
            ]);

            $data = [
                'username' => $this->editUsername,
                'email' => $this->editEmail ?: null,
                'notes' => $this->editNotes ?: null,
                'recovery_codes' => $this->editRecoveryCodes ?: null,
            ];

            if ($this->editTotpSecret !== '') {
                $data['totp_secret'] = $this->editTotpSecret;
            }
        }

        if ($this->editPassword !== '') {
            $data['password'] = $this->editPassword;
        }

        $this->credential = UpdateCredential::run($this->credential, auth()->user(), $data);

        if (array_key_exists('password', $data)) {
            $this->revealedPassword = null;
        }

        $this->editPassword = '';
        $this->editTotpSecret = '';

        Flux::modal('edit-credential-modal')->close();
        Flux::toast('Credential updated.', 'Done', variant: 'success');
        unset($this->assignedPositions, $this->availablePositions);
    }

    public function confirmDelete(): void
    {
        $this->authorize('delete', $this->credential);
        Flux::modal('delete-confirm-modal')->show();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->credential);

        DeleteCredential::run($this->credential, auth()->user());

        Flux::modal('delete-confirm-modal')->close();

        $this->redirect(route('vault.index'), navigate: true);
    }

    public function openManagePositions(): void
    {
        $this->authorize('managePositions', $this->credential);
        $this->addPositionId = null;
        Flux::modal('manage-positions-modal')->show();
    }

    public function addPosition(): void
    {
        $this->authorize('managePositions', $this->credential);

        $this->validate(['addPositionId' => 'required|integer|exists:staff_positions,id']);

        $current = $this->credential->staffPositions->pluck('id')->toArray();
        if (! in_array($this->addPositionId, $current)) {
            $current[] = $this->addPositionId;
            AssignCredentialPositions::run($this->credential, auth()->user(), $current);
            $this->credential = $this->credential->fresh();
        }

        $this->addPositionId = null;
        unset($this->assignedPositions, $this->availablePositions);
        Flux::toast('Position added.', 'Done', variant: 'success');
    }

    public function removePosition(int $positionId): void
    {
        $this->authorize('managePositions', $this->credential);

        $current = $this->credential->staffPositions->pluck('id')
            ->filter(fn ($id) => $id !== $positionId)
            ->values()
            ->toArray();

        AssignCredentialPositions::run($this->credential, auth()->user(), $current);
        $this->credential = $this->credential->fresh();

        unset($this->assignedPositions, $this->availablePositions);
        Flux::toast('Position removed.', 'Done', variant: 'success');
    }
}; ?>

<div>
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
                    <flux:link href="{{ $credential->website_url }}" target="_blank" rel="noopener noreferrer" class="text-sm">
                        {{ $credential->website_url }}
                    </flux:link>
                @endif
            </div>
            <div class="flex gap-2">
                @can('update', $credential)
                    <flux:button variant="ghost" icon="pencil-square" wire:click="openEdit">Edit</flux:button>
                @endcan
                @can('managePositions', $credential)
                    <flux:button variant="ghost" icon="user-group" wire:click="openManagePositions">Manage Access</flux:button>
                @endcan
                @can('delete', $credential)
                    <flux:button variant="danger" icon="trash" wire:click="confirmDelete">Delete</flux:button>
                @endcan
            </div>
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
                    @if ($revealedPassword !== null)
                        <flux:text class="mt-1 font-mono break-all">{{ $revealedPassword }}</flux:text>
                    @else
                        <div class="mt-1 flex items-center gap-2">
                            <flux:text class="font-mono text-zinc-400">••••••••••••</flux:text>
                            <flux:button size="xs" variant="ghost" wire:click="revealPassword">Reveal</flux:button>
                        </div>
                        <flux:text variant="subtle" class="mt-1 text-xs">Re-authentication required to reveal</flux:text>
                    @endif
                </div>

                @if ($credential->getRawOriginal('totp_secret'))
                    <div>
                        <flux:text variant="subtle" class="text-xs uppercase tracking-wide">TOTP</flux:text>
                        <div class="mt-1 flex items-center gap-2">
                            <flux:badge color="blue" size="sm">TOTP configured</flux:badge>
                            <flux:button size="xs" variant="ghost" wire:click="showTotp">Show Code</flux:button>
                        </div>
                        <flux:text variant="subtle" class="mt-1 text-xs">Re-authentication required to view code</flux:text>
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

        @can('managePositions', $credential)
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">Position Access</flux:heading>
                </div>
                @if ($this->assignedPositions->isEmpty())
                    <flux:text variant="subtle" class="text-sm">No positions assigned — only Vault Managers can view this credential.</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Position</flux:table.column>
                            <flux:table.column>Held By</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->assignedPositions as $position)
                                <flux:table.row wire:key="pos-{{ $position->id }}">
                                    <flux:table.cell>{{ $position->title }}</flux:table.cell>
                                    <flux:table.cell>
                                        {{ $position->user?->name ?? '(vacant)' }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button size="sm" variant="danger" icon="x-mark" wire:click="removePosition({{ $position->id }})">Remove</flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        @endcan

        <flux:text variant="subtle" class="text-xs">
            Added by {{ $credential->createdBy->name }} on {{ $credential->created_at->format('M j, Y') }}
            @if ($credential->updatedBy)
                · Last updated by {{ $credential->updatedBy->name }} on {{ $credential->updated_at->format('M j, Y') }}
            @endif
        </flux:text>
    </div>

    {{-- Edit modal --}}
    @can('update', $credential)
        <flux:modal name="edit-credential-modal" class="w-full max-w-2xl">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Credential</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @if ($this->isVaultManager)
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
                    @endif

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
    @endcan

    @can('delete', $credential)
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

    {{-- Re-auth modal --}}
    <flux:modal name="reauth-modal" class="w-full max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm Your Identity</flux:heading>
                <flux:text class="mt-2">
                    @if ($reauthPurpose === 'totp')
                        Enter your Lighthouse password to view the current TOTP code.
                    @else
                        Enter your Lighthouse password to unlock the vault and reveal this credential.
                    @endif
                </flux:text>
            </div>

            <flux:field>
                <flux:label>Password</flux:label>
                <flux:input wire:model="reauthPassword" type="password" wire:keydown.enter="reauth" autofocus />
                @if ($reauthError)
                    <flux:error>{{ $reauthError }}</flux:error>
                @endif
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal('reauth-modal').close()">Cancel</flux:button>
                <flux:button variant="primary" wire:click="reauth">
                    {{ $reauthPurpose === 'totp' ? 'View Code' : 'Unlock & Reveal' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- TOTP modal --}}
    @if ($totpCode !== null)
        <flux:modal name="totp-modal" class="w-full max-w-sm">
            <div class="space-y-6">
                <flux:heading size="lg">TOTP Code</flux:heading>

                <div class="text-center">
                    <flux:text class="font-mono text-4xl font-bold tracking-widest">{{ $totpCode }}</flux:text>
                    <flux:text variant="subtle" class="mt-2 text-sm">
                        Expires in <span wire:poll.1000ms="refreshTotp">{{ $totpSecondsRemaining }}</span> seconds
                    </flux:text>
                </div>

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="closeTotp">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    @can('managePositions', $credential)
        {{-- Manage position access modal --}}
        <flux:modal name="manage-positions-modal" class="w-full max-w-lg">
            <div class="space-y-6">
                <flux:heading size="lg">Manage Position Access</flux:heading>

                <div class="space-y-2">
                    <flux:text variant="subtle" class="text-sm">Add a staff position to grant everyone who holds that position access to view and update this credential.</flux:text>
                </div>

                @if ($this->availablePositions->isNotEmpty())
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <flux:select wire:model="addPositionId">
                                <flux:select.option value="">Select a position...</flux:select.option>
                                @foreach ($this->availablePositions as $position)
                                    <flux:select.option value="{{ $position->id }}">
                                        {{ $position->title }}
                                        @if ($position->user)
                                            ({{ $position->user->name }})
                                        @else
                                            (vacant)
                                        @endif
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button variant="primary" wire:click="addPosition">Add</flux:button>
                    </div>
                    <flux:error name="addPositionId" />
                @else
                    <flux:text variant="subtle" class="text-sm">All positions have been assigned access.</flux:text>
                @endif

                <div class="flex justify-end">
                    <flux:button variant="ghost" x-on:click="$flux.modal('manage-positions-modal').close()">Done</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
