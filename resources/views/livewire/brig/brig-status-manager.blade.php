<?php

use App\Actions\UpdateBrigStatus;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public User $user;

    public string $brigReason = '';

    public string $brigExpiresAt = '';

    public bool $brigPermanent = false;

    public bool $brigNotify = true;

    public string $brigReleaseReason = '';

    public function mount(User $user): void
    {
        $this->authorize('put-in-brig');

        $this->user = $user;
        $this->brigReason = $user->brig_reason ?? '';
        $this->brigExpiresAt = $user->brig_expires_at
            ? $user->brig_expires_at->setTimezone($this->adminTimezone())->format('Y-m-d\TH:i')
            : '';
        $this->brigPermanent = $user->permanent_brig_at !== null;
    }

    public function updatedBrigPermanent(): void
    {
        // Always force notify ON when the permanent flag is changed
        $this->brigNotify = true;
    }

    public function saveStatus(): void
    {
        $this->authorize('put-in-brig');

        $this->validate([
            'brigReason' => 'required|string|min:5',
            'brigExpiresAt' => 'nullable|date',
        ]);

        $originalPermanent = $this->user->permanent_brig_at !== null;
        $permanent = null;

        if ($this->brigPermanent && ! $originalPermanent) {
            $permanent = true;
        } elseif (! $this->brigPermanent && $originalPermanent) {
            $permanent = false;
        }

        $newExpiresAt = false; // false = no change
        if (! $this->brigPermanent) {
            $newExpiresAt = filled($this->brigExpiresAt)
                ? Carbon::parse($this->brigExpiresAt, $this->adminTimezone())->utc()
                : null;
        }

        UpdateBrigStatus::run(
            target: $this->user,
            admin: Auth::user(),
            newReason: $this->brigReason !== ($this->user->brig_reason ?? '') ? $this->brigReason : null,
            newExpiresAt: $newExpiresAt,
            permanent: $permanent,
            notify: $this->brigNotify,
        );

        $this->user->refresh();
        Flux::toast('Brig status updated.', 'Saved', variant: 'success');
        $this->dispatch('brig-status-updated');
    }

    public function quickRelease(): void
    {
        $this->authorize('put-in-brig');

        $this->validate([
            'brigReleaseReason' => 'required|string|min:5',
        ]);

        UpdateBrigStatus::run(
            target: $this->user,
            admin: Auth::user(),
            releaseReason: $this->brigReleaseReason,
        );

        Flux::toast($this->user->name.' has been released from the Brig.', 'Released', variant: 'success');
        $this->dispatch('brig-status-updated');
    }

    private function adminTimezone(): string
    {
        return Auth::user()->timezone ?? 'UTC';
    }
}; ?>

<div class="space-y-6">
    @can('put-in-brig')
        {{-- Brig Type (read-only) --}}
        <div class="flex items-center gap-3">
            <flux:text class="font-medium">Brig Type:</flux:text>
            <flux:badge color="red">{{ $user->brig_type?->label() ?? 'Discipline' }}</flux:badge>
            @if($user->permanent_brig_at)
                <flux:badge color="zinc">Permanent</flux:badge>
            @endif
        </div>

        {{-- Status update form --}}
        <div class="space-y-4">
            <flux:heading size="sm">Update Status</flux:heading>

            <flux:field>
                <flux:label>Brig Reason <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="brigReason" rows="3" placeholder="Reason for brig placement..." />
                <flux:error name="brigReason" />
            </flux:field>

            <flux:field x-show="!$wire.brigPermanent">
                <flux:label>Expires At</flux:label>
                <flux:description>Leave blank for indefinite (no expiry).</flux:description>
                <flux:input wire:model="brigExpiresAt" type="datetime-local" />
                <flux:error name="brigExpiresAt" />
            </flux:field>

            <div class="space-y-2">
                <flux:checkbox wire:model.live="brigPermanent" label="Permanent Confinement" />

                <flux:checkbox
                    wire:model="brigNotify"
                    label="Notify user of updates?"
                    :disabled="$brigPermanent !== ($user->permanent_brig_at !== null)"
                />
            </div>

            <flux:button wire:click="saveStatus" wire:loading.attr="disabled" wire:target="saveStatus" variant="primary">
                Save Changes
            </flux:button>
        </div>

        <flux:separator />

        {{-- Quick Release --}}
        <div class="space-y-4">
            <flux:heading size="sm" class="text-red-600 dark:text-red-400">Quick Release</flux:heading>
            <flux:text variant="subtle" class="text-sm">Release this user from the brig immediately. A reason is required.</flux:text>

            <flux:field>
                <flux:label>Release Reason <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="brigReleaseReason" rows="2" placeholder="Reason for release..." />
                <flux:error name="brigReleaseReason" />
            </flux:field>

            <flux:button wire:click="quickRelease" wire:loading.attr="disabled" wire:target="quickRelease" variant="danger">
                Release from Brig
            </flux:button>
        </div>
    @endcan
</div>
