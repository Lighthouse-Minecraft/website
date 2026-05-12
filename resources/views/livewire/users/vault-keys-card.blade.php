<?php

use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public int $userId;

    public function mount(User $user): void
    {
        $this->authorize('view-vault');
        $this->userId = $user->id;
    }

    public function getUserProperty(): User
    {
        return User::with(['staffPosition.credentials:id,name'])->findOrFail($this->userId);
    }

    public function getCredentialsProperty(): \Illuminate\Support\Collection
    {
        $position = $this->user->staffPosition;

        if (! $position) {
            return collect();
        }

        return $position->credentials->pluck('name');
    }
}; ?>

<div>
    <flux:card class="w-full">
        <flux:heading size="md">Vault Keys</flux:heading>
        <flux:separator variant="subtle" class="my-2" />

        @if($this->credentials->isEmpty())
            <flux:text variant="subtle" class="text-center py-4">No vault access assigned to this staff position.</flux:text>
        @else
            <ul class="space-y-1">
                @foreach($this->credentials as $name)
                    <li wire:key="vault-key-{{ $loop->index }}" class="flex items-center gap-2">
                        <flux:icon name="key" class="w-4 h-4 text-zinc-400 shrink-0" />
                        <flux:text class="text-sm">{{ $name }}</flux:text>
                    </li>
                @endforeach
            </ul>
        @endif
    </flux:card>
</div>
