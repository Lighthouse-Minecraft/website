<?php

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
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
            'account_type' => 'minecraft_accounts.account_type',
            'verified_at' => 'minecraft_accounts.verified_at',
            default => 'minecraft_accounts.username',
        };

        return MinecraftAccount::query()
            ->join('users', 'minecraft_accounts.user_id', '=', 'users.id')
            ->select('minecraft_accounts.*', 'users.name as user_name')
            ->orderBy($sortColumn, $this->sortDirection)
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Minecraft Users</flux:heading>

    <flux:table :paginate="$this->accounts">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'username'" :direction="$sortDirection" wire:click="sort('username')">MC Username</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'user_name'" :direction="$sortDirection" wire:click="sort('user_name')">User</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'account_type'" :direction="$sortDirection" wire:click="sort('account_type')">Type</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'verified_at'" :direction="$sortDirection" wire:click="sort('verified_at')">Date Verified</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->accounts as $account)
                <flux:table.row :key="$account->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $account->username }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:link href="{{ route('profile.show', $account->user_id) }}">{{ $account->user_name }}</flux:link>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:badge variant="pill" size="sm" color="{{ $account->account_type === MinecraftAccountType::Java ? 'green' : 'blue' }}">
                            {{ $account->account_type->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $account->verified_at->format('M j, Y') }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
