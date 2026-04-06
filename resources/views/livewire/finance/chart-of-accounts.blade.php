<?php

use App\Models\FinancialAccount;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {

    public string $newCode = '';
    public string $newName = '';
    public string $newType = '';
    public string $newSubtype = '';
    public string $newDescription = '';
    public string $newNormalBalance = '';
    public string $newFundType = 'unrestricted';
    public bool $newIsBankAccount = false;

    public ?int $editId = null;
    public string $editName = '';
    public string $editSubtype = '';
    public string $editDescription = '';
    public string $editFundType = 'unrestricted';
    public bool $editIsBankAccount = false;

    public function getAccountsByTypeProperty(): array
    {
        return FinancialAccount::orderBy('code')
            ->get()
            ->groupBy('type')
            ->toArray();
    }

    public function addAccount(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'newCode'          => 'required|integer|min:1|unique:financial_accounts,code',
            'newName'          => 'required|string|max:255',
            'newType'          => 'required|in:asset,liability,net_assets,revenue,expense',
            'newNormalBalance' => 'required|in:debit,credit',
            'newFundType'      => 'required|in:unrestricted,restricted',
            'newSubtype'       => 'nullable|string|max:100',
            'newDescription'   => 'nullable|string|max:500',
        ]);

        FinancialAccount::create([
            'code'           => (int) $this->newCode,
            'name'           => $this->newName,
            'type'           => $this->newType,
            'subtype'        => $this->newSubtype ?: null,
            'description'    => $this->newDescription ?: null,
            'normal_balance' => $this->newNormalBalance,
            'fund_type'      => $this->newFundType,
            'is_bank_account' => $this->newIsBankAccount,
            'is_active'      => true,
        ]);

        Flux::modal('add-account-modal')->close();
        Flux::toast('Account added successfully.', 'Done', variant: 'success');
        $this->reset(['newCode', 'newName', 'newType', 'newSubtype', 'newDescription', 'newNormalBalance', 'newFundType', 'newIsBankAccount']);
    }

    public function openEdit(int $id): void
    {
        $this->authorize('finance-manage');

        $account = FinancialAccount::findOrFail($id);
        $this->editId          = $account->id;
        $this->editName        = $account->name;
        $this->editSubtype     = $account->subtype ?? '';
        $this->editDescription = $account->description ?? '';
        $this->editFundType    = $account->fund_type;
        $this->editIsBankAccount = $account->is_bank_account;

        Flux::modal('edit-account-modal')->show();
    }

    public function saveEdit(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'editName'        => 'required|string|max:255',
            'editSubtype'     => 'nullable|string|max:100',
            'editDescription' => 'nullable|string|max:500',
            'editFundType'    => 'required|in:unrestricted,restricted',
        ]);

        $account = FinancialAccount::findOrFail($this->editId);
        $account->update([
            'name'           => $this->editName,
            'subtype'        => $this->editSubtype ?: null,
            'description'    => $this->editDescription ?: null,
            'fund_type'      => $this->editFundType,
            'is_bank_account' => $this->editIsBankAccount,
        ]);

        Flux::modal('edit-account-modal')->close();
        Flux::toast('Account updated.', 'Done', variant: 'success');
        $this->reset(['editId', 'editName', 'editSubtype', 'editDescription', 'editFundType', 'editIsBankAccount']);
    }

    public function deactivate(int $id): void
    {
        $this->authorize('finance-manage');

        $account = FinancialAccount::findOrFail($id);
        $account->update(['is_active' => false]);

        Flux::toast("{$account->name} deactivated.", 'Done', variant: 'success');
    }

    public function reactivate(int $id): void
    {
        $this->authorize('finance-manage');

        $account = FinancialAccount::findOrFail($id);
        $account->update(['is_active' => true]);

        Flux::toast("{$account->name} reactivated.", 'Done', variant: 'success');
    }
}; ?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Chart of Accounts</flux:heading>
            <flux:text variant="subtle">Manage the account structure for the financial ledger.</flux:text>
        </div>
        @can('finance-manage')
            <flux:modal.trigger name="add-account-modal">
                <flux:button variant="primary" icon="plus">Add Account</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    @php
        $typeLabels = [
            'asset'      => 'Assets',
            'liability'  => 'Liabilities',
            'net_assets' => 'Net Assets',
            'revenue'    => 'Revenue',
            'expense'    => 'Expenses',
        ];
    @endphp

    @foreach ($typeLabels as $typeKey => $typeLabel)
        @if (!empty($this->accountsByType[$typeKey]))
            <flux:card>
                <flux:heading size="md" class="mb-4">{{ $typeLabel }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Code</flux:table.column>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Subtype</flux:table.column>
                        <flux:table.column>Normal Balance</flux:table.column>
                        <flux:table.column>Fund Type</flux:table.column>
                        <flux:table.column>Bank</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        @can('finance-manage')
                            <flux:table.column>Actions</flux:table.column>
                        @endcan
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->accountsByType[$typeKey] as $account)
                            <flux:table.row wire:key="account-{{ $account['id'] }}">
                                <flux:table.cell>
                                    <flux:badge color="zinc" size="sm">{{ $account['code'] }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if (!$account['is_active'])
                                        <span class="line-through text-zinc-400">{{ $account['name'] }}</span>
                                    @else
                                        {{ $account['name'] }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $account['subtype'] ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ ucfirst($account['normal_balance']) }}</flux:table.cell>
                                <flux:table.cell>{{ ucfirst($account['fund_type']) }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($account['is_bank_account'])
                                        <flux:badge color="blue" size="sm" icon="check">Yes</flux:badge>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($account['is_active'])
                                        <flux:badge color="green" size="sm">Active</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">Inactive</flux:badge>
                                    @endif
                                </flux:table.cell>
                                @can('finance-manage')
                                    <flux:table.cell>
                                        <div class="flex gap-2">
                                            <flux:button size="sm" icon="pencil-square" wire:click="openEdit({{ $account['id'] }})">Edit</flux:button>
                                            @if ($account['is_active'])
                                                <flux:button size="sm" icon="archive-box" variant="danger" wire:click="deactivate({{ $account['id'] }})">Deactivate</flux:button>
                                            @else
                                                <flux:button size="sm" icon="arrow-path" wire:click="reactivate({{ $account['id'] }})">Reactivate</flux:button>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                @endcan
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    @endforeach

    {{-- Add Account Modal --}}
    @can('finance-manage')
        <flux:modal name="add-account-modal" class="w-full max-w-lg">
            <div class="space-y-6">
                <flux:heading size="lg">Add Account</flux:heading>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Code <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model.live="newCode" type="number" min="1" placeholder="e.g. 6000" />
                        <flux:error name="newCode" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Normal Balance <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="newNormalBalance">
                            <flux:select.option value="">— Select —</flux:select.option>
                            <flux:select.option value="debit">Debit</flux:select.option>
                            <flux:select.option value="credit">Credit</flux:select.option>
                        </flux:select>
                        <flux:error name="newNormalBalance" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model.live="newName" placeholder="e.g. Equipment" />
                    <flux:error name="newName" />
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Type <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="newType">
                            <flux:select.option value="">— Select —</flux:select.option>
                            <flux:select.option value="asset">Asset</flux:select.option>
                            <flux:select.option value="liability">Liability</flux:select.option>
                            <flux:select.option value="net_assets">Net Assets</flux:select.option>
                            <flux:select.option value="revenue">Revenue</flux:select.option>
                            <flux:select.option value="expense">Expense</flux:select.option>
                        </flux:select>
                        <flux:error name="newType" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Fund Type</flux:label>
                        <flux:select wire:model.live="newFundType">
                            <flux:select.option value="unrestricted">Unrestricted</flux:select.option>
                            <flux:select.option value="restricted">Restricted</flux:select.option>
                        </flux:select>
                        <flux:error name="newFundType" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Subtype</flux:label>
                    <flux:input wire:model.live="newSubtype" placeholder="e.g. equipment" />
                    <flux:error name="newSubtype" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model.live="newDescription" rows="2" />
                    <flux:error name="newDescription" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model.live="newIsBankAccount" id="new-is-bank" />
                    <flux:label for="new-is-bank">This is a bank account (requires reconciliation before period close)</flux:label>
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('add-account-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="addAccount">Add Account</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Edit Account Modal --}}
        <flux:modal name="edit-account-modal" class="w-full max-w-lg">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Account</flux:heading>

                <flux:field>
                    <flux:label>Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model.live="editName" />
                    <flux:error name="editName" />
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Fund Type</flux:label>
                        <flux:select wire:model.live="editFundType">
                            <flux:select.option value="unrestricted">Unrestricted</flux:select.option>
                            <flux:select.option value="restricted">Restricted</flux:select.option>
                        </flux:select>
                        <flux:error name="editFundType" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Subtype</flux:label>
                        <flux:input wire:model.live="editSubtype" />
                        <flux:error name="editSubtype" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model.live="editDescription" rows="2" />
                    <flux:error name="editDescription" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model.live="editIsBankAccount" id="edit-is-bank" />
                    <flux:label for="edit-is-bank">This is a bank account</flux:label>
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('edit-account-modal').close()">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="saveEdit">Save Changes</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
