<?php

use App\Actions\ArchiveFinancialOrganization;
use App\Actions\ArchiveFinancialTag;
use App\Actions\CreateFinancialOrganization;
use App\Actions\CreateFinancialTag;
use App\Actions\DeleteFinancialTransaction;
use App\Actions\RecordFinancialTransaction;
use App\Actions\UpdateFinancialTransaction;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialOrganization;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    // ── Entry form ────────────────────────────────────────────────────────────
    public string $type = 'expense';

    public string $accountId = '';

    public string $targetAccountId = '';

    public string $amount = '';

    public string $transactedAt = '';

    public string $categoryId = '';

    public string $notes = '';

    public array $selectedTagIds = [];

    public ?int $organizationId = null;

    public string $organizationName = '';

    public string $organizationSearch = '';

    // ── Ledger filters ────────────────────────────────────────────────────────
    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public string $filterAccountId = '';

    public string $filterCategoryId = '';

    public string $filterTagId = '';

    // ── Edit transaction ──────────────────────────────────────────────────────
    public ?int $editTxId = null;

    public string $editType = 'expense';

    public string $editAccountId = '';

    public string $editAmount = '';

    public string $editDate = '';

    public string $editCategoryId = '';

    public string $editNotes = '';

    public array $editTagIds = [];

    public ?int $editOrganizationId = null;

    public string $editOrganizationName = '';

    public string $editOrganizationSearch = '';

    // ── Tag management ────────────────────────────────────────────────────────
    public string $newTagName = '';

    public function mount(): void
    {
        $this->transactedAt = now()->format('Y-m-d');
    }

    // ── Reference data ────────────────────────────────────────────────────────

    public function accounts(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialAccount::where('is_archived', false)->orderBy('name')->get();
    }

    public function allAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialAccount::orderBy('name')->get();
    }

    public function groupedCategoriesForType(string $type): array
    {
        $all = FinancialCategory::where('is_archived', false)
            ->where('type', $type)
            ->orderBy('sort_order')
            ->get();

        $topLevel = $all->whereNull('parent_id');
        $byParent = $all->whereNotNull('parent_id')->groupBy('parent_id');

        $groups = [];
        foreach ($topLevel as $parent) {
            $children = $byParent->get($parent->id, collect());
            if ($children->isEmpty()) {
                $groups[] = ['type' => 'option', 'id' => $parent->id, 'name' => $parent->name];
            } else {
                $groups[] = [
                    'type' => 'group',
                    'label' => $parent->name,
                    'options' => $children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
                ];
            }
        }

        return $groups;
    }

    public function tags(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialTag::where('is_archived', false)->orderBy('name')->get();
    }

    public function organizations(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialOrganization::where('is_archived', false)->orderBy('name')->get();
    }

    public function filteredOrganizations(string $search): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialOrganization::where('is_archived', false)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();
    }

    public function allTopLevelCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialCategory::whereNull('parent_id')
            ->where('is_archived', false)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();
    }

    // ── Ledger ────────────────────────────────────────────────────────────────

    public function ledger(): \Illuminate\Database\Eloquent\Collection
    {
        $query = FinancialTransaction::with([
            'account',
            'targetAccount',
            'category.parent',
            'tags',
            'enteredBy',
            'organization',
        ])->orderBy('transacted_at', 'desc')->orderBy('id', 'desc');

        if ($this->filterDateFrom !== '') {
            $query->where('transacted_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo !== '') {
            $query->where('transacted_at', '<=', $this->filterDateTo);
        }
        if ($this->filterAccountId !== '') {
            $query->where('account_id', (int) $this->filterAccountId);
        }
        if ($this->filterCategoryId !== '') {
            // match top-level or any subcategory under it
            $subIds = FinancialCategory::where('parent_id', (int) $this->filterCategoryId)->pluck('id');
            $ids = $subIds->prepend((int) $this->filterCategoryId);
            $query->whereIn('financial_category_id', $ids);
        }
        if ($this->filterTagId !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('financial_tags.id', (int) $this->filterTagId));
        }

        return $query->get();
    }

    // ── Entry form lifecycle ──────────────────────────────────────────────────

    public function updatedType(): void
    {
        $this->reset(['categoryId', 'targetAccountId']);
    }

    public function updatedEditType(): void
    {
        $this->editCategoryId = '';
    }

    // ── Submit new transaction ────────────────────────────────────────────────

    public function submitTransaction(): void
    {
        $this->authorize('financials-treasurer');

        $isTransfer = $this->type === 'transfer';

        $rules = [
            'type' => 'required|in:income,expense,transfer',
            'accountId' => 'required|integer|exists:financial_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'transactedAt' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($isTransfer) {
            $rules['targetAccountId'] = 'required|integer|exists:financial_accounts,id|different:accountId';
        } else {
            $rules['categoryId'] = 'required|integer|exists:financial_categories,id';
            $rules['selectedTagIds'] = 'array';
            $rules['selectedTagIds.*'] = 'integer|exists:financial_tags,id';
        }

        $this->validate($rules);

        $amountCents = (int) round((float) $this->amount * 100);

        if ($isTransfer) {
            RecordFinancialTransaction::run(
                auth()->user(),
                (int) $this->accountId,
                'transfer',
                $amountCents,
                $this->transactedAt,
                null,
                $this->notes ?: null,
                [],
                (int) $this->targetAccountId,
            );
        } else {
            RecordFinancialTransaction::run(
                auth()->user(),
                (int) $this->accountId,
                $this->type,
                $amountCents,
                $this->transactedAt,
                (int) $this->categoryId,
                $this->notes ?: null,
                $this->selectedTagIds,
                null,
                $this->organizationId,
            );
        }

        Flux::toast('Transaction recorded.', 'Success', variant: 'success');
        $this->reset(['accountId', 'targetAccountId', 'amount', 'categoryId', 'notes', 'selectedTagIds', 'organizationId', 'organizationName', 'organizationSearch']);
        $this->transactedAt = now()->format('Y-m-d');
        Flux::modal('record-transaction')->close();
    }

    // ── Edit transaction ──────────────────────────────────────────────────────

    public function openEditModal(int $id): void
    {
        $this->authorize('financials-treasurer');

        $tx = FinancialTransaction::with('tags')->findOrFail($id);

        if ($tx->isInPublishedMonth()) {
            Flux::toast('Cannot edit a transaction in a published month.', 'Error', variant: 'danger');

            return;
        }

        if ($tx->type === 'transfer') {
            Flux::toast('Transfer transactions cannot be edited. Delete and re-enter if needed.', 'Error', variant: 'danger');

            return;
        }

        $this->editTxId = $id;
        $this->editType = $tx->type;
        $this->editAccountId = (string) $tx->account_id;
        $this->editAmount = number_format($tx->amount / 100, 2);
        $this->editDate = $tx->transacted_at->format('Y-m-d');
        $this->editNotes = $tx->notes ?? '';
        $this->editTagIds = $tx->tags->pluck('id')->toArray();

        if ($tx->financial_category_id !== null) {
            $this->editCategoryId = (string) $tx->financial_category_id;
        }

        if ($tx->organization_id !== null) {
            $this->editOrganizationId = $tx->organization_id;
            $this->editOrganizationName = $tx->organization?->name ?? '';
        }

        Flux::modal('edit-tx-modal')->show();
    }

    public function updateTransaction(): void
    {
        $this->authorize('financials-treasurer');

        $this->validate([
            'editType' => 'required|in:income,expense',
            'editAccountId' => 'required|integer|exists:financial_accounts,id',
            'editAmount' => 'required|numeric|min:0.01',
            'editDate' => 'required|date',
            'editCategoryId' => 'required|integer|exists:financial_categories,id',
            'editNotes' => 'nullable|string|max:1000',
            'editTagIds' => 'array',
            'editTagIds.*' => 'integer|exists:financial_tags,id',
        ]);

        $tx = FinancialTransaction::findOrFail($this->editTxId);
        $editAmountCents = (int) round((float) $this->editAmount * 100);

        try {
            UpdateFinancialTransaction::run(
                $tx,
                (int) $this->editAccountId,
                $this->editType,
                $editAmountCents,
                $this->editDate,
                (int) $this->editCategoryId,
                null,
                $this->editNotes ?: null,
                $this->editTagIds,
                $this->editOrganizationId,
            );
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');

            return;
        }

        Flux::modal('edit-tx-modal')->close();
        Flux::toast('Transaction updated.', 'Success', variant: 'success');
        $this->reset(['editTxId', 'editType', 'editAccountId', 'editAmount', 'editDate', 'editCategoryId', 'editNotes', 'editTagIds', 'editOrganizationId', 'editOrganizationName', 'editOrganizationSearch']);
        $this->editType = 'expense';
    }

    // ── Delete transaction ────────────────────────────────────────────────────

    public function deleteTransaction(int $id): void
    {
        $this->authorize('financials-treasurer');

        $tx = FinancialTransaction::findOrFail($id);

        try {
            DeleteFinancialTransaction::run($tx);
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');

            return;
        }

        Flux::toast('Transaction deleted.', 'Success', variant: 'success');
    }

    // ── Tag management ────────────────────────────────────────────────────────

    public function createTag(): void
    {
        $this->authorize('financials-manage');

        $this->validate([
            'newTagName' => 'required|string|max:100|unique:financial_tags,name',
        ]);

        CreateFinancialTag::run($this->newTagName, auth()->user());

        Flux::toast('Tag created.', 'Success', variant: 'success');
        $this->newTagName = '';
    }

    public function archiveTag(int $id): void
    {
        $this->authorize('financials-manage');

        $tag = FinancialTag::findOrFail($id);
        ArchiveFinancialTag::run($tag);

        Flux::toast('Tag archived.', 'Success', variant: 'success');
    }

    // ── Organization picker ───────────────────────────────────────────────────

    public function selectOrganization(int $id, string $name): void
    {
        $this->organizationId = $id;
        $this->organizationName = $name;
        $this->organizationSearch = '';
        Flux::modal('org-picker')->close();
    }

    public function clearOrganization(): void
    {
        $this->organizationId = null;
        $this->organizationName = '';
    }

    public function createOrganizationInline(): void
    {
        $this->authorize('financials-treasurer');

        $this->validate([
            'organizationSearch' => 'required|string|max:255|unique:financial_organizations,name',
        ]);

        $org = CreateFinancialOrganization::run($this->organizationSearch, auth()->user());

        $this->selectOrganization($org->id, $org->name);
    }

    public function selectEditOrganization(int $id, string $name): void
    {
        $this->editOrganizationId = $id;
        $this->editOrganizationName = $name;
        $this->editOrganizationSearch = '';
        Flux::modal('edit-org-picker')->close();
    }

    public function clearEditOrganization(): void
    {
        $this->editOrganizationId = null;
        $this->editOrganizationName = '';
    }

    public function createEditOrganizationInline(): void
    {
        $this->authorize('financials-treasurer');

        $this->validate([
            'editOrganizationSearch' => 'required|string|max:255|unique:financial_organizations,name',
        ]);

        $org = CreateFinancialOrganization::run($this->editOrganizationSearch, auth()->user());

        $this->selectEditOrganization($org->id, $org->name);
    }

    // ── Organization management ───────────────────────────────────────────────

    public function archiveOrganization(int $id): void
    {
        $this->authorize('financials-manage');

        $org = FinancialOrganization::findOrFail($id);
        ArchiveFinancialOrganization::run($org);

        Flux::toast('Organization archived.', 'Success', variant: 'success');
    }
}; ?>

<div class="space-y-8">

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

    {{-- Account Balances --}}
    <div class="space-y-3">
        <flux:heading size="xl">Finance Dashboard</flux:heading>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($this->accounts() as $account)
                <flux:card wire:key="balance-{{ $account->id }}" class="text-center">
                    <flux:text variant="subtle" class="text-sm">{{ $account->name }}</flux:text>
                    <flux:heading size="lg">${{ number_format($account->currentBalance() / 100, 2) }}</flux:heading>
                    <flux:text variant="subtle" class="text-xs">{{ ucwords(str_replace('-', ' ', $account->type)) }}</flux:text>
                </flux:card>
            @endforeach
        </div>
    </div>

    {{-- Add Transaction Button (treasurer only) --}}
    @can('financials-treasurer')
        <div>
            <flux:button wire:click="$flux.modal('record-transaction').show()" variant="primary" icon="plus">
                Add Transaction
            </flux:button>
        </div>
    @endcan

    {{-- Manage Tags (financials-manage only) --}}
    @can('financials-manage')
        <flux:card class="space-y-4">
            <flux:heading size="lg">Manage Tags</flux:heading>

            <form wire:submit.prevent="createTag" class="flex gap-3 items-end">
                <flux:field class="flex-1">
                    <flux:label>New Tag Name</flux:label>
                    <flux:input wire:model="newTagName" placeholder="Tag name…" />
                    <flux:error name="newTagName" />
                </flux:field>
                <flux:button type="submit" variant="primary" icon="plus">Create Tag</flux:button>
            </form>

            @if ($this->tags()->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->tags() as $tag)
                            <flux:table.row wire:key="tag-{{ $tag->id }}">
                                <flux:table.cell>{{ $tag->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="sm" variant="danger" icon="archive-box"
                                        wire:click="archiveTag({{ $tag->id }})"
                                        wire:confirm="Archive tag '{{ $tag->name }}'? It will no longer appear on the transaction form.">
                                        Archive
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text variant="subtle">No tags yet. Create one above.</flux:text>
            @endif
        </flux:card>
    @endcan

    {{-- Manage Organizations (financials-manage only) --}}
    @can('financials-manage')
        <flux:card class="space-y-4">
            <flux:heading size="lg">Manage Organizations</flux:heading>

            @if ($this->organizations()->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->organizations() as $org)
                            <flux:table.row wire:key="org-{{ $org->id }}">
                                <flux:table.cell>{{ $org->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="sm" variant="danger" icon="archive-box"
                                        wire:click="archiveOrganization({{ $org->id }})"
                                        wire:confirm="Archive '{{ $org->name }}'? It will no longer appear in the picker.">
                                        Archive
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text variant="subtle">No organizations yet. Add one when recording a transaction.</flux:text>
            @endif
        </flux:card>
    @endcan

    {{-- Record Transaction Modal --}}
    @can('financials-treasurer')
        <flux:modal name="record-transaction" class="w-full max-w-2xl space-y-5">
            <flux:heading size="lg">Record Transaction</flux:heading>

            <form wire:submit.prevent="submitTransaction" class="space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Type <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="type">
                            <flux:select.option value="expense">Expense</flux:select.option>
                            <flux:select.option value="income">Income</flux:select.option>
                            <flux:select.option value="transfer">Transfer</flux:select.option>
                        </flux:select>
                        <flux:error name="type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ $type === 'transfer' ? 'From Account' : 'Account' }} <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model="accountId">
                            <flux:select.option value="">— Select Account —</flux:select.option>
                            @foreach ($this->accounts() as $account)
                                <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="accountId" />
                    </flux:field>
                </div>

                @if ($type === 'transfer')
                    <flux:field>
                        <flux:label>To Account <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model="targetAccountId">
                            <flux:select.option value="">— Select Account —</flux:select.option>
                            @foreach ($this->accounts() as $account)
                                <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="targetAccountId" />
                    </flux:field>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Amount ($) <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="amount" type="number" step="0.01" min="0.01" placeholder="10.00" />
                        <flux:error name="amount" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Date <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="transactedAt" type="date" />
                        <flux:error name="transactedAt" />
                    </flux:field>
                </div>

                @if ($type !== 'transfer')
                    <flux:field>
                        <flux:label>Category <span class="text-red-500">*</span></flux:label>
                        <select wire:model="categoryId"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">— Select Category —</option>
                            @foreach ($this->groupedCategoriesForType($type) as $item)
                                @if ($item['type'] === 'group')
                                    <optgroup label="{{ $item['label'] }}">
                                        @foreach ($item['options'] as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @else
                                    <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                        <flux:error name="categoryId" />
                    </flux:field>
                @endif

                @if ($type !== 'transfer')
                    <flux:field>
                        <flux:label>Tags</flux:label>
                        <div class="flex flex-wrap gap-2 mt-1">
                            @foreach ($this->tags() as $tag)
                                <label class="flex items-center gap-1 text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="selectedTagIds" value="{{ $tag->id }}"
                                        class="rounded border-zinc-600" />
                                    {{ $tag->name }}
                                </label>
                            @endforeach
                        </div>
                        <flux:error name="selectedTagIds" />
                    </flux:field>
                @endif

                @if ($type !== 'transfer')
                    <flux:field>
                        <flux:label>Organization</flux:label>
                        <div class="flex gap-2 items-center">
                            @if ($organizationId)
                                <flux:badge>{{ $organizationName }}</flux:badge>
                                <flux:button size="sm" variant="ghost" wire:click="clearOrganization" icon="x-mark">Clear</flux:button>
                            @else
                                <flux:button size="sm" wire:click="$flux.modal('org-picker').show()" icon="building-office">Add Organization</flux:button>
                            @endif
                        </div>
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="2" placeholder="Optional notes…" />
                    <flux:error name="notes" />
                </flux:field>

                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary" icon="plus">Record Transaction</flux:button>
                    <flux:button x-on:click="$flux.modal('record-transaction').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Organization Picker Modal (for new transaction) --}}
        <flux:modal name="org-picker" class="w-full max-w-lg space-y-4">
            <flux:heading size="lg">Select Organization</flux:heading>

            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live="organizationSearch" placeholder="Type to search…" autofocus />
            </flux:field>

            @php $filtered = $this->filteredOrganizations($organizationSearch); @endphp

            @if ($filtered->isNotEmpty())
                <div class="space-y-1 max-h-64 overflow-y-auto">
                    @foreach ($filtered as $org)
                        <button type="button"
                            wire:click="selectOrganization({{ $org->id }}, '{{ addslashes($org->name) }}')"
                            class="w-full text-left px-3 py-2 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 text-sm">
                            {{ $org->name }}
                        </button>
                    @endforeach
                </div>
            @elseif ($organizationSearch !== '')
                <flux:text variant="subtle">No match found.</flux:text>
                <flux:button wire:click="createOrganizationInline" variant="primary" size="sm" icon="plus">
                    Add "{{ $organizationSearch }}"
                </flux:button>
                <flux:error name="organizationSearch" />
            @else
                <flux:text variant="subtle">No organizations yet. Type a name to create one.</flux:text>
            @endif
        </flux:modal>
    @endcan

    {{-- Transaction Ledger --}}
    <div class="space-y-4">
        <flux:heading size="lg">Transaction Ledger</flux:heading>

        {{-- Filters --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <flux:field>
                <flux:label>From</flux:label>
                <flux:input wire:model.live="filterDateFrom" type="date" />
            </flux:field>
            <flux:field>
                <flux:label>To</flux:label>
                <flux:input wire:model.live="filterDateTo" type="date" />
            </flux:field>
            <flux:field>
                <flux:label>Account</flux:label>
                <flux:select wire:model.live="filterAccountId">
                    <flux:select.option value="">All Accounts</flux:select.option>
                    @foreach ($this->allAccounts() as $account)
                        <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>Category</flux:label>
                <flux:select wire:model.live="filterCategoryId">
                    <flux:select.option value="">All Categories</flux:select.option>
                    @foreach ($this->allTopLevelCategories() as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->type }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
        <flux:field class="w-48">
            <flux:label>Tag</flux:label>
            <flux:select wire:model.live="filterTagId">
                <flux:select.option value="">All Tags</flux:select.option>
                @foreach ($this->tags() as $tag)
                    <flux:select.option value="{{ $tag->id }}">{{ $tag->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Account</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Amount</flux:table.column>
                <flux:table.column>Organization</flux:table.column>
                <flux:table.column>Tags</flux:table.column>
                <flux:table.column>Notes</flux:table.column>
                <flux:table.column>By</flux:table.column>
                @can('financials-treasurer')
                    <flux:table.column>Actions</flux:table.column>
                @endcan
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->ledger() as $tx)
                    <flux:table.row wire:key="tx-{{ $tx->id }}">
                        <flux:table.cell>{{ $tx->transacted_at->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($tx->type === 'transfer')
                                {{ $tx->account?->name }} → {{ $tx->targetAccount?->name }}
                            @else
                                {{ $tx->account?->name }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($tx->type === 'income')
                                <flux:badge variant="success">Income</flux:badge>
                            @elseif ($tx->type === 'expense')
                                <flux:badge variant="danger">Expense</flux:badge>
                            @else
                                <flux:badge variant="zinc">Transfer</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($tx->category)
                                @if ($tx->category->parent)
                                    {{ $tx->category->parent->name }} / {{ $tx->category->name }}
                                @else
                                    {{ $tx->category->name }}
                                @endif
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>${{ number_format($tx->amount / 100, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($tx->organization)
                                <flux:badge size="sm" variant="zinc">{{ $tx->organization->name }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($tx->tags as $tag)
                                    <flux:badge size="sm">{{ $tag->name }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $tx->notes }}</flux:table.cell>
                        <flux:table.cell>{{ $tx->enteredBy?->name }}</flux:table.cell>
                        @can('financials-treasurer')
                            <flux:table.cell>
                                @unless ($tx->isInPublishedMonth())
                                    <div class="flex gap-2">
                                        @unless ($tx->type === 'transfer')
                                            <flux:button size="sm" icon="pencil-square" wire:click="openEditModal({{ $tx->id }})">Edit</flux:button>
                                        @endunless
                                        <flux:button size="sm" variant="danger" icon="trash"
                                            wire:click="deleteTransaction({{ $tx->id }})"
                                            wire:confirm="Delete this transaction? This cannot be undone.">
                                            Delete
                                        </flux:button>
                                    </div>
                                @else
                                    <flux:text variant="subtle" class="text-xs">Published</flux:text>
                                @endunless
                            </flux:table.cell>
                        @endcan
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10">
                            <flux:text variant="subtle">No transactions found.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Edit Transaction Modal --}}
    @can('financials-treasurer')
        <flux:modal name="edit-tx-modal" class="w-full max-w-2xl space-y-5">
            <flux:heading size="lg">Edit Transaction</flux:heading>

            <form wire:submit.prevent="updateTransaction" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Type <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="editType">
                            <flux:select.option value="expense">Expense</flux:select.option>
                            <flux:select.option value="income">Income</flux:select.option>
                        </flux:select>
                        <flux:error name="editType" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Account <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model="editAccountId">
                            <flux:select.option value="">— Select Account —</flux:select.option>
                            @foreach ($this->accounts() as $account)
                                <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editAccountId" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Amount ($) <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="editAmount" type="number" step="0.01" min="0.01" />
                        <flux:error name="editAmount" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Date <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="editDate" type="date" />
                        <flux:error name="editDate" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Category <span class="text-red-500">*</span></flux:label>
                    <select wire:model="editCategoryId"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <option value="">— Select Category —</option>
                        @foreach ($this->groupedCategoriesForType($editType) as $item)
                            @if ($item['type'] === 'group')
                                <optgroup label="{{ $item['label'] }}">
                                    @foreach ($item['options'] as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                    @endforeach
                                </optgroup>
                            @else
                                <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    <flux:error name="editCategoryId" />
                </flux:field>

                <flux:field>
                    <flux:label>Tags</flux:label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($this->tags() as $tag)
                            <label class="flex items-center gap-1 text-sm cursor-pointer">
                                <input type="checkbox" wire:model="editTagIds" value="{{ $tag->id }}"
                                    class="rounded border-zinc-600" />
                                {{ $tag->name }}
                            </label>
                        @endforeach
                    </div>
                    <flux:error name="editTagIds" />
                </flux:field>

                <flux:field>
                    <flux:label>Organization</flux:label>
                    <div class="flex gap-2 items-center">
                        @if ($editOrganizationId)
                            <flux:badge>{{ $editOrganizationName }}</flux:badge>
                            <flux:button size="sm" variant="ghost" wire:click="clearEditOrganization" icon="x-mark">Clear</flux:button>
                        @else
                            <flux:button size="sm" wire:click="$flux.modal('edit-org-picker').show()" icon="building-office">Add Organization</flux:button>
                        @endif
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="editNotes" rows="2" />
                    <flux:error name="editNotes" />
                </flux:field>

                <div class="flex gap-3 pt-2">
                    <flux:button type="submit" variant="primary">Save Changes</flux:button>
                    <flux:button x-on:click="$flux.modal('edit-tx-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Organization Picker Modal (for edit transaction) --}}
        <flux:modal name="edit-org-picker" class="w-full max-w-lg space-y-4">
            <flux:heading size="lg">Select Organization</flux:heading>

            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input wire:model.live="editOrganizationSearch" placeholder="Type to search…" />
            </flux:field>

            @php $editFiltered = $this->filteredOrganizations($editOrganizationSearch); @endphp

            @if ($editFiltered->isNotEmpty())
                <div class="space-y-1 max-h-64 overflow-y-auto">
                    @foreach ($editFiltered as $org)
                        <button type="button"
                            wire:click="selectEditOrganization({{ $org->id }}, '{{ addslashes($org->name) }}')"
                            class="w-full text-left px-3 py-2 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 text-sm">
                            {{ $org->name }}
                        </button>
                    @endforeach
                </div>
            @elseif ($editOrganizationSearch !== '')
                <flux:text variant="subtle">No match found.</flux:text>
                <flux:button wire:click="createEditOrganizationInline" variant="primary" size="sm" icon="plus">
                    Add "{{ $editOrganizationSearch }}"
                </flux:button>
                <flux:error name="editOrganizationSearch" />
            @else
                <flux:text variant="subtle">No organizations yet. Type a name to create one.</flux:text>
            @endif
        </flux:modal>
    @endcan

</div>
