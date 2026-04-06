<?php

use App\Actions\ArchiveFinancialAccount;
use App\Actions\CreateFinancialAccount;
use App\Actions\UpdateFinancialAccount;
use App\Models\FinancialAccount;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $newName = '';
    public string $newType = 'checking';
    public int $newOpeningBalance = 0;

    public ?int $editId = null;
    public string $editName = '';

    public function accounts(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialAccount::orderBy('name')->get();
    }

    public function createAccount(): void
    {
        $this->authorize('financials-manage');

        $this->validate([
            'newName' => 'required|string|max:255',
            'newType' => 'required|in:checking,savings,payment-processor,cash',
            'newOpeningBalance' => 'required|integer|min:0',
        ]);

        CreateFinancialAccount::run($this->newName, $this->newType, $this->newOpeningBalance);

        Flux::modal('create-account-modal')->close();
        Flux::toast('Account created.', 'Success', variant: 'success');
        $this->reset(['newName', 'newType', 'newOpeningBalance']);
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('financials-manage');

        $account = FinancialAccount::findOrFail($id);
        $this->editId = $id;
        $this->editName = $account->name;

        Flux::modal('edit-account-modal')->show();
    }

    public function updateAccount(): void
    {
        $this->authorize('financials-manage');

        $this->validate([
            'editName' => 'required|string|max:255',
        ]);

        $account = FinancialAccount::findOrFail($this->editId);
        UpdateFinancialAccount::run($account, $this->editName);

        Flux::modal('edit-account-modal')->close();
        Flux::toast('Account updated.', 'Success', variant: 'success');
        $this->reset(['editId', 'editName']);
    }

    public function archiveAccount(int $id): void
    {
        $this->authorize('financials-manage');

        $account = FinancialAccount::findOrFail($id);
        ArchiveFinancialAccount::run($account);

        Flux::toast('Account archived.', 'Success', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    {{-- Finance Navigation --}}
    <div class="flex flex-wrap gap-2">
        <flux:button href="{{ route('finances.dashboard') }}" wire:navigate size="sm" icon="banknotes">Dashboard</flux:button>
        <flux:button href="{{ route('finances.budget') }}" wire:navigate size="sm" icon="calculator">Budget</flux:button>
        <flux:button href="{{ route('finances.reports') }}" wire:navigate size="sm" icon="document-text">Period Reports</flux:button>
        @can('financials-manage')
            <flux:button href="{{ route('finances.board-reports') }}" wire:navigate size="sm" icon="chart-bar">Board Reports</flux:button>
            <flux:button href="{{ route('finances.accounts') }}" wire:navigate size="sm" icon="building-library">Accounts</flux:button>
            <flux:button href="{{ route('finances.categories') }}" wire:navigate size="sm" icon="tag">Categories</flux:button>
        @endcan
    </div>

    <div class="flex items-center justify-between">
        <flux:heading size="xl">Financial Accounts</flux:heading>
        @can('financials-manage')
            <flux:modal.trigger name="create-account-modal">
                <flux:button variant="primary" icon="plus">New Account</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Balance</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            @can('financials-manage')
                <flux:table.column>Actions</flux:table.column>
            @endcan
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->accounts() as $account)
                <flux:table.row wire:key="account-{{ $account->id }}">
                    <flux:table.cell>{{ $account->name }}</flux:table.cell>
                    <flux:table.cell>{{ ucwords(str_replace('-', ' ', $account->type)) }}</flux:table.cell>
                    <flux:table.cell>${{ number_format($account->currentBalance() / 100, 2) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($account->is_archived)
                            <flux:badge variant="warning">Archived</flux:badge>
                        @else
                            <flux:badge variant="success">Active</flux:badge>
                        @endif
                    </flux:table.cell>
                    @can('financials-manage')
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="sm" icon="pencil-square" wire:click="openEditModal({{ $account->id }})">Rename</flux:button>
                                @unless ($account->is_archived)
                                    <flux:button size="sm" variant="danger" icon="archive-box" wire:click="archiveAccount({{ $account->id }})"
                                        wire:confirm="Archive this account? It will be hidden from transaction entry forms but historical data is preserved.">
                                        Archive
                                    </flux:button>
                                @endunless
                            </div>
                        </flux:table.cell>
                    @endcan
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- Create Account Modal --}}
    @can('financials-manage')
        <flux:modal name="create-account-modal" class="w-full max-w-md space-y-6">
            <flux:heading size="lg">New Account</flux:heading>

            <form wire:submit.prevent="createAccount" class="space-y-4">
                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="newName" placeholder="e.g. RelayFi Checking" />
                    <flux:error name="newName" />
                </flux:field>

                <flux:field>
                    <flux:label>Type <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="newType">
                        <flux:select.option value="checking">Checking</flux:select.option>
                        <flux:select.option value="savings">Savings</flux:select.option>
                        <flux:select.option value="payment-processor">Payment Processor</flux:select.option>
                        <flux:select.option value="cash">Cash</flux:select.option>
                    </flux:select>
                    <flux:error name="newType" />
                </flux:field>

                <flux:field>
                    <flux:label>Opening Balance (cents)</flux:label>
                    <flux:description>Enter amount in cents (e.g. 10000 = $100.00)</flux:description>
                    <flux:input wire:model="newOpeningBalance" type="number" min="0" />
                    <flux:error name="newOpeningBalance" />
                </flux:field>

                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Create Account</flux:button>
                    <flux:button x-on:click="$flux.modal('create-account-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Edit Account Modal --}}
        <flux:modal name="edit-account-modal" class="w-full max-w-md space-y-6">
            <flux:heading size="lg">Rename Account</flux:heading>

            <form wire:submit.prevent="updateAccount" class="space-y-4">
                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                    <flux:button x-on:click="$flux.modal('edit-account-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
