<?php

use App\Models\DiscordAccount;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $sortBy = 'username';
    public string $sortDirection = 'asc';

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[\Livewire\Attributes\Computed]
    public function accounts()
    {
        $sortColumn = match ($this->sortBy) {
            'user_name' => 'users.name',
            'global_name' => 'discord_accounts.global_name',
            'status' => 'discord_accounts.status',
            'verified_at' => 'discord_accounts.verified_at',
            default => 'discord_accounts.username',
        };

        return DiscordAccount::query()
            ->join('users', 'discord_accounts.user_id', '=', 'users.id')
            ->select('discord_accounts.*', 'users.name as user_name')
            ->orderBy($sortColumn, $this->sortDirection)
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Discord Users</flux:heading>

    <flux:table :paginate="$this->accounts">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'username'" :direction="$sortDirection" wire:click="sort('username')">Discord Username</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'global_name'" :direction="$sortDirection" wire:click="sort('global_name')">Display Name</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'user_name'" :direction="$sortDirection" wire:click="sort('user_name')">Site User</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'verified_at'" :direction="$sortDirection" wire:click="sort('verified_at')">Linked Date</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->accounts as $account)
                <flux:table.row wire:key="discord-account-{{ $account->id }}">
                    <flux:table.cell class="whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <img src="{{ $account->avatarUrl() }}" alt="{{ $account->username }}" class="w-6 h-6 rounded-full" />
                            {{ $account->username }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $account->global_name ?? '—' }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:link href="{{ route('profile.show', $account->user_id) }}">{{ $account->user_name }}</flux:link>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:badge color="{{ $account->status->color() }}" size="sm">
                            {{ $account->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $account->verified_at ? $account->verified_at->format('M j, Y') : '—' }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
