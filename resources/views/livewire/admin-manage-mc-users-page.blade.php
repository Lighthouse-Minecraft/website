<?php

use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'username';
    public string $sortDirection = 'asc';
    public ?MinecraftAccount $selectedAccount = null;

    /**
     * Reset the pagination page when the search filter changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Set the current sort column for the accounts table and toggle or reset the sort direction.
     *
     * If called with the same column as the current sort, flips between 'asc' and 'desc'.
     * If called with a different column, sets that column and resets the direction to 'asc'.
     *
     * @param string $column The column identifier to sort by (e.g. 'username', 'user_name', 'account_type', 'uuid', 'verified_at').
     */
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Authorizes viewing Minecraft accounts, loads the account with its user relation by ID, assigns it to the component, and opens the account detail modal if found.
     *
     * Performs an authorization check for viewing Minecraft accounts. If a matching account exists it is stored in `$this->selectedAccount` and the `mc-account-detail` modal is shown; if no account is found, no modal is opened.
     *
     * @param int $accountId The ID of the Minecraft account to load and display.
     */
    public function showAccount(int $accountId): void
    {
        $this->authorize('viewAny', MinecraftAccount::class);

        $this->selectedAccount = MinecraftAccount::with('user')->find($accountId);

        if ($this->selectedAccount) {
            $this->modal('mc-account-detail')->show();
        }
    }

    public function reactivateMinecraftAccount(int $accountId): void
    {
        $this->authorize('viewAny', MinecraftAccount::class);

        $account = MinecraftAccount::findOrFail($accountId);
        $result = \App\Actions\ReactivateMinecraftAccount::run($account, Auth::user());

        if ($result['success']) {
            Flux::toast($result['message'], variant: 'success');
            $this->selectedAccount = null;
            $this->modal('mc-account-detail')->close();
        } else {
            Flux::toast($result['message'], variant: 'danger');
        }
    }

    public function forceDeleteMinecraftAccount(int $accountId): void
    {
        $this->authorize('viewAny', MinecraftAccount::class);

        $account = MinecraftAccount::findOrFail($accountId);
        $result = \App\Actions\ForceDeleteMinecraftAccount::run($account, Auth::user());

        if ($result['success']) {
            Flux::toast($result['message'], variant: 'success');
            $this->selectedAccount = null;
            $this->modal('mc-account-detail')->close();
        } else {
            Flux::toast($result['message'], variant: 'danger');
        }
    }

    /**
     * Retrieve a paginated list of MinecraftAccount records joined with their user name,
     * filtered by the search term and ordered according to the component's current sort column and direction.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator Paginated MinecraftAccount models with an added `user_name` attribute from the joined users table.
     */
    #[\Livewire\Attributes\Computed]
    public function accounts()
    {
        $sortColumn = match ($this->sortBy) {
            'user_name' => 'users.name',
            'account_type' => 'minecraft_accounts.account_type',
            'status' => 'minecraft_accounts.status',
            'uuid' => 'minecraft_accounts.uuid',
            'verified_at' => 'minecraft_accounts.verified_at',
            default => 'minecraft_accounts.username',
        };

        $direction = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

        return MinecraftAccount::query()
            ->join('users', 'minecraft_accounts.user_id', '=', 'users.id')
            ->select('minecraft_accounts.*', 'users.name as user_name')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('minecraft_accounts.username', 'like', "%{$this->search}%")
                    ->orWhere('users.name', 'like', "%{$this->search}%")
                    ->orWhere('minecraft_accounts.uuid', 'like', "%{$this->search}%");
            }))
            ->orderBy($sortColumn, $direction)
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Minecraft Users</flux:heading>

    <flux:input wire:model.live.debounce.400ms="search" placeholder="Search username, user, or UUID..." icon="magnifying-glass" class="max-w-sm" />

    <flux:table :paginate="$this->accounts">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'username'" :direction="$sortDirection" wire:click="sort('username')">MC Username</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'user_name'" :direction="$sortDirection" wire:click="sort('user_name')">User</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'account_type'" :direction="$sortDirection" wire:click="sort('account_type')">Type</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'uuid'" :direction="$sortDirection" wire:click="sort('uuid')">UUID</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'verified_at'" :direction="$sortDirection" wire:click="sort('verified_at')">Date Verified</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->accounts as $account)
                <flux:table.row wire:key="account-{{ $account->id }}">
                    <flux:table.cell class="whitespace-nowrap">
                        <button wire:click="showAccount({{ $account->id }})"
                                class="flex items-center gap-2 text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                            @if($account->avatar_url)
                                <img src="{{ $account->avatar_url }}" alt="{{ $account->username }}" class="w-6 h-6 rounded" />
                            @endif
                            {{ $account->username }}
                        </button>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:link href="{{ route('profile.show', $account->user_id) }}">{{ $account->user_name }}</flux:link>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:badge variant="pill" size="sm" color="{{ $account->account_type === MinecraftAccountType::Java ? 'green' : 'blue' }}">
                            {{ $account->account_type->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        <flux:badge size="sm" color="{{ $account->status->color() }}">
                            {{ $account->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap font-mono text-xs text-zinc-500 dark:text-zinc-400">…{{ Str::afterLast($account->uuid, '-') }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $account->verified_at ? $account->verified_at->format('M j, Y') : '—' }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <x-minecraft.mc-account-detail-modal :account="$selectedAccount" />
</div>