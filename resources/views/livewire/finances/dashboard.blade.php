<?php

use App\Actions\DeleteFinancialTransaction;
use App\Actions\RecordFinancialTransaction;
use App\Actions\UpdateFinancialTransaction;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
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

    public string $subcategoryId = '';

    public string $notes = '';

    public array $selectedTagIds = [];

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

    public string $editSubcategoryId = '';

    public string $editNotes = '';

    public array $editTagIds = [];

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

    public function topLevelCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialCategory::whereNull('parent_id')
            ->where('type', $this->type)
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();
    }

    public function editTopLevelCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialCategory::whereNull('parent_id')
            ->where('type', $this->editType)
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();
    }

    public function subcategories(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->categoryId === '') {
            return collect();
        }

        return FinancialCategory::where('parent_id', (int) $this->categoryId)
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();
    }

    public function editSubcategories(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->editCategoryId === '') {
            return collect();
        }

        return FinancialCategory::where('parent_id', (int) $this->editCategoryId)
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();
    }

    public function tags(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialTag::where('is_archived', false)->orderBy('name')->get();
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
        $this->reset(['categoryId', 'subcategoryId', 'targetAccountId']);
    }

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    public function updatedEditType(): void
    {
        $this->reset(['editCategoryId', 'editSubcategoryId']);
    }

    public function updatedEditCategoryId(): void
    {
        $this->editSubcategoryId = '';
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
            $rules['subcategoryId'] = 'nullable|integer|exists:financial_categories,id';
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
            $effectiveCategoryId = $this->subcategoryId !== '' ? (int) $this->subcategoryId : (int) $this->categoryId;

            RecordFinancialTransaction::run(
                auth()->user(),
                (int) $this->accountId,
                $this->type,
                $amountCents,
                $this->transactedAt,
                $effectiveCategoryId,
                $this->notes ?: null,
                $this->selectedTagIds,
            );
        }

        Flux::toast('Transaction recorded.', 'Success', variant: 'success');
        $this->reset(['accountId', 'targetAccountId', 'amount', 'categoryId', 'subcategoryId', 'notes', 'selectedTagIds']);
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

        // Resolve category/subcategory
        if ($tx->financial_category_id !== null) {
            $cat = FinancialCategory::find($tx->financial_category_id);
            if ($cat) {
                if ($cat->parent_id !== null) {
                    $this->editCategoryId = (string) $cat->parent_id;
                    $this->editSubcategoryId = (string) $cat->id;
                } else {
                    $this->editCategoryId = (string) $cat->id;
                    $this->editSubcategoryId = '';
                }
            }
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
            'editSubcategoryId' => 'nullable|integer|exists:financial_categories,id',
            'editNotes' => 'nullable|string|max:1000',
            'editTagIds' => 'array',
            'editTagIds.*' => 'integer|exists:financial_tags,id',
        ]);

        $tx = FinancialTransaction::findOrFail($this->editTxId);
        $effectiveCategoryId = $this->editSubcategoryId !== '' ? (int) $this->editSubcategoryId : (int) $this->editCategoryId;
        $editAmountCents = (int) round((float) $this->editAmount * 100);

        try {
            UpdateFinancialTransaction::run(
                $tx,
                (int) $this->editAccountId,
                $this->editType,
                $editAmountCents,
                $this->editDate,
                $effectiveCategoryId,
                null,
                $this->editNotes ?: null,
                $this->editTagIds,
            );
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');

            return;
        }

        Flux::modal('edit-tx-modal')->close();
        Flux::toast('Transaction updated.', 'Success', variant: 'success');
        $this->reset(['editTxId', 'editType', 'editAccountId', 'editAmount', 'editDate', 'editCategoryId', 'editSubcategoryId', 'editNotes', 'editTagIds']);
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
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Category <span class="text-red-500">*</span></flux:label>
                            <flux:select wire:model.live="categoryId">
                                <flux:select.option value="">— Select Category —</flux:select.option>
                                @foreach ($this->topLevelCategories() as $category)
                                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="categoryId" />
                        </flux:field>

                        @if ($categoryId !== '' && $this->subcategories()->isNotEmpty())
                            <flux:field>
                                <flux:label>Subcategory</flux:label>
                                <flux:select wire:model="subcategoryId">
                                    <flux:select.option value="">— None —</flux:select.option>
                                    @foreach ($this->subcategories() as $sub)
                                        <flux:select.option value="{{ $sub->id }}">{{ $sub->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="subcategoryId" />
                            </flux:field>
                        @endif
                    </div>
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
                        <flux:table.cell colspan="9">
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

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Category <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="editCategoryId">
                            <flux:select.option value="">— Select Category —</flux:select.option>
                            @foreach ($this->editTopLevelCategories() as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="editCategoryId" />
                    </flux:field>

                    @if ($editCategoryId !== '' && $this->editSubcategories()->isNotEmpty())
                        <flux:field>
                            <flux:label>Subcategory</flux:label>
                            <flux:select wire:model="editSubcategoryId">
                                <flux:select.option value="">— None —</flux:select.option>
                                @foreach ($this->editSubcategories() as $sub)
                                    <flux:select.option value="{{ $sub->id }}">{{ $sub->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="editSubcategoryId" />
                        </flux:field>
                    @endif
                </div>

                @if ($this->tags()->isNotEmpty())
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
                @endif

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
    @endcan

</div>
