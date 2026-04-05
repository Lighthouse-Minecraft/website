<?php

use App\Actions\DeleteFinancialTransaction;
use App\Actions\PublishPeriodReport;
use App\Actions\UpdateFinancialTransaction;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialOrganization;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\MonthlyBudget;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $month = '';      // Y-m
    public string $monthStart = ''; // Y-m-d
    public string $monthEnd = '';   // Y-m-d
    public bool $isPublished = false;

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

    public function mount(string $month): void
    {
        try {
            $date = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception $e) {
            abort(404);
        }

        $this->month = $month;
        $this->monthStart = $date->toDateString();
        $this->monthEnd = Carbon::parse($this->monthStart)->endOfMonth()->toDateString();

        $this->isPublished = FinancialPeriodReport::whereDate('month', $this->monthStart)
            ->whereNotNull('published_at')
            ->exists();

        if ($this->isPublished) {
            $this->authorize('financials-view');
        } else {
            $this->authorize('financials-treasurer');
        }
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    public function summary(): array
    {
        // For published months, return the immutable snapshot.
        $report = FinancialPeriodReport::whereDate('month', $this->monthStart)
            ->whereNotNull('published_at')
            ->first();

        if ($report && $report->summary_snapshot !== null) {
            $snap = $report->summary_snapshot;
            $snap['accountBalances'] = collect($snap['accountBalances']);

            return $snap;
        }

        $income = (int) FinancialTransaction::where('type', 'income')
            ->whereBetween('transacted_at', [$this->monthStart, $this->monthEnd])
            ->sum('amount');

        $expense = (int) FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$this->monthStart, $this->monthEnd])
            ->sum('amount');

        $accounts = FinancialAccount::orderBy('name')->get();

        $accountBalances = $accounts->map(function ($account) {
            $credits = (int) $account->transactions()->where('type', 'income')
                ->where('transacted_at', '<=', $this->monthEnd)->sum('amount');
            $debits = (int) $account->transactions()->where('type', 'expense')
                ->where('transacted_at', '<=', $this->monthEnd)->sum('amount');
            $transfersOut = (int) $account->transactions()->where('type', 'transfer')
                ->where('transacted_at', '<=', $this->monthEnd)->sum('amount');
            $transfersIn = (int) $account->incomingTransfers()->where('type', 'transfer')
                ->where('transacted_at', '<=', $this->monthEnd)->sum('amount');
            $balance = $account->opening_balance + $credits - $debits - $transfersOut + $transfersIn;

            return ['name' => $account->name, 'balance' => $balance];
        });

        $categories = FinancialCategory::whereNull('parent_id')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        $budgetVariances = [];
        foreach ($categories as $cat) {
            $planned = (int) optional(MonthlyBudget::whereDate('month', $this->monthStart)
                ->where('financial_category_id', $cat->id)
                ->first())->planned_amount;

            $subIds = FinancialCategory::where('parent_id', $cat->id)->pluck('id');
            $ids = $subIds->prepend($cat->id);
            $actual = (int) FinancialTransaction::whereIn('financial_category_id', $ids)
                ->whereBetween('transacted_at', [$this->monthStart, $this->monthEnd])
                ->whereIn('type', ['income', 'expense'])
                ->sum('amount');

            if ($planned > 0 || $actual > 0) {
                $budgetVariances[] = [
                    'name' => $cat->name,
                    'type' => $cat->type,
                    'planned' => $planned,
                    'actual' => $actual,
                    'variance' => $cat->type === 'income'
                        ? $actual - $planned
                        : $planned - $actual,
                ];
            }
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'accountBalances' => $accountBalances,
            'budgetVariances' => $budgetVariances,
        ];
    }

    // ── Transactions ──────────────────────────────────────────────────────────

    #[Computed]
    public function transactions(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialTransaction::with([
            'account',
            'targetAccount',
            'category.parent',
            'tags',
            'enteredBy',
            'organization',
        ])
            ->whereBetween('transacted_at', [$this->monthStart, $this->monthEnd])
            ->orderBy('transacted_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    // ── Reference data for edit modal ─────────────────────────────────────────

    public function accounts(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialAccount::where('is_archived', false)->orderBy('name')->get();
    }

    #[Computed]
    public function tags(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialTag::where('is_archived', false)->orderBy('name')->get();
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

    public function filteredOrganizations(string $search): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialOrganization::where('is_archived', false)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    public function publish(): void
    {
        $this->authorize('financials-treasurer');

        try {
            PublishPeriodReport::run($this->monthStart, auth()->user());
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');

            return;
        }

        Flux::toast('Period report published.', 'Success', variant: 'success');
        $this->redirect(route('finances.reports'), navigate: true);
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
        $this->editAmount = number_format($tx->amount / 100, 2, '.', '');
        $this->editDate = $tx->transacted_at->format('Y-m-d');
        $this->editNotes = $tx->notes ?? '';
        $this->editTagIds = $tx->tags->pluck('id')->toArray();
        $this->editOrganizationId = null;
        $this->editOrganizationName = '';
        $this->editOrganizationSearch = '';

        if ($tx->financial_category_id !== null) {
            $this->editCategoryId = (string) $tx->financial_category_id;
        }

        if ($tx->organization_id !== null) {
            $this->editOrganizationId = $tx->organization_id;
            $this->editOrganizationName = $tx->organization?->name ?? '';
        }

        Flux::modal('detail-edit-tx-modal')->show();
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

        Flux::modal('detail-edit-tx-modal')->close();
        Flux::toast('Transaction updated.', 'Success', variant: 'success');
        $this->reset(['editTxId', 'editType', 'editAccountId', 'editAmount', 'editDate', 'editCategoryId', 'editNotes', 'editTagIds', 'editOrganizationId', 'editOrganizationName', 'editOrganizationSearch']);
        $this->editType = 'expense';
        unset($this->transactions);
    }

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
        unset($this->transactions);
    }

    public function openEditOrgPickerModal(): void
    {
        $this->authorize('financials-treasurer');
        Flux::modal('detail-edit-org-picker')->show();
    }

    public function selectEditOrganization(int $id): void
    {
        $org = FinancialOrganization::findOrFail($id);
        $this->editOrganizationId = $org->id;
        $this->editOrganizationName = $org->name;
        $this->editOrganizationSearch = '';
        Flux::modal('detail-edit-org-picker')->close();
    }

    public function clearEditOrganization(): void
    {
        $this->editOrganizationId = null;
        $this->editOrganizationName = '';
    }

    public function updatedEditType(): void
    {
        $this->editCategoryId = '';
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
            <flux:button href="{{ route('finances.categories') }}" wire:navigate size="sm" icon="tag">Categories &amp; Tags</flux:button>
        @endcan
    </div>

    {{-- Page heading --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:heading size="xl">
                Period Report — {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
            </flux:heading>
            @if ($isPublished)
                <flux:badge variant="success">Published</flux:badge>
            @else
                <flux:badge variant="zinc">Unpublished</flux:badge>
            @endif
        </div>
        <flux:button href="{{ route('finances.reports') }}" wire:navigate size="sm" icon="arrow-left" variant="ghost">
            Back to Reports
        </flux:button>
    </div>

    @php $summary = $this->summary(); @endphp

    {{-- Summary cards --}}
    <div class="grid grid-cols-3 gap-4">
        <flux:card class="text-center">
            <flux:text variant="subtle" class="text-sm">Total Income</flux:text>
            <flux:heading size="lg" class="text-green-500">${{ number_format($summary['income'] / 100, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text variant="subtle" class="text-sm">Total Expenses</flux:text>
            <flux:heading size="lg" class="text-red-500">${{ number_format($summary['expense'] / 100, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="text-center">
            <flux:text variant="subtle" class="text-sm">Net Change</flux:text>
            <flux:heading size="lg" class="{{ $summary['net'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                {{ $summary['net'] >= 0 ? '+' : '' }}${{ number_format(abs($summary['net']) / 100, 2) }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Account Balances --}}
    @if ($summary['accountBalances']->isNotEmpty())
        <div>
            <flux:heading size="sm" class="mb-2">Account Balances (as of end of month)</flux:heading>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($summary['accountBalances'] as $ab)
                    <div class="flex justify-between text-sm">
                        <span>{{ $ab['name'] }}</span>
                        <span>${{ number_format($ab['balance'] / 100, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Budget Variance --}}
    @if (!empty($summary['budgetVariances']))
        <div>
            <flux:heading size="sm" class="mb-2">Budget Variance</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Category</flux:table.column>
                    <flux:table.column>Planned</flux:table.column>
                    <flux:table.column>Actual</flux:table.column>
                    <flux:table.column>Variance</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($summary['budgetVariances'] as $bv)
                        <flux:table.row>
                            <flux:table.cell>{{ $bv['name'] }}</flux:table.cell>
                            <flux:table.cell>{{ $bv['planned'] > 0 ? '$' . number_format($bv['planned'] / 100, 2) : '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $bv['actual'] > 0 ? '$' . number_format($bv['actual'] / 100, 2) : '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @php $v = $bv['variance']; @endphp
                                <span class="{{ $v >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                    {{ $v >= 0 ? '+' : '' }}${{ number_format(abs($v) / 100, 2) }}
                                </span>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    {{-- Transaction List --}}
    <div>
        <flux:heading size="sm" class="mb-2">Transactions</flux:heading>
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
                @if (!$isPublished)
                    @can('financials-treasurer')
                        <flux:table.column>Actions</flux:table.column>
                    @endcan
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->transactions as $tx)
                    <flux:table.row wire:key="detail-tx-{{ $tx->id }}">
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
                        @if (!$isPublished)
                            @can('financials-treasurer')
                                <flux:table.cell>
                                    <div class="flex gap-2">
                                        @if ($tx->type === 'transfer')
                                            <flux:tooltip content="Transfers cannot be edited — delete and re-enter if needed.">
                                                <flux:button size="sm" icon="pencil-square" disabled>Edit</flux:button>
                                            </flux:tooltip>
                                        @else
                                            <flux:button size="sm" icon="pencil-square" wire:click="openEditModal({{ $tx->id }})">Edit</flux:button>
                                        @endif
                                        <flux:button size="sm" variant="danger" icon="trash"
                                            wire:click="deleteTransaction({{ $tx->id }})"
                                            wire:confirm="Delete this transaction? This cannot be undone.">
                                            Delete
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            @endcan
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9">
                            <flux:text variant="subtle">No transactions found for this month.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Action buttons --}}
    <div class="flex gap-3">
        @if ($isPublished)
            <flux:button href="{{ route('finances.reports.pdf', ['month' => $month]) }}" target="_blank" icon="arrow-down-tray">
                Download PDF
            </flux:button>
        @else
            @can('financials-treasurer')
                <flux:button wire:click="publish" variant="primary" icon="lock-closed"
                    wire:confirm="Publish this report? All transactions in this month will become read-only.">
                    Confirm &amp; Publish
                </flux:button>
            @endcan
        @endif
        <flux:button href="{{ route('finances.reports') }}" wire:navigate variant="ghost" icon="arrow-left">
            Back to Reports
        </flux:button>
    </div>

    {{-- Edit Transaction Modal (unpublished months, treasurer only) --}}
    @if (!$isPublished)
        @can('financials-treasurer')
            <flux:modal name="detail-edit-tx-modal" class="w-full max-w-2xl space-y-5">
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
                            @foreach ($this->tags as $tag)
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
                                <flux:button size="sm" wire:click="openEditOrgPickerModal" icon="building-office">Add Organization</flux:button>
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
                        <flux:button x-on:click="$flux.modal('detail-edit-tx-modal').close()" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            </flux:modal>

            {{-- Organization Picker Modal --}}
            <flux:modal name="detail-edit-org-picker" class="w-full max-w-lg space-y-4">
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
                                wire:click="selectEditOrganization({{ $org->id }})"
                                class="w-full text-left px-3 py-2 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 text-sm">
                                {{ $org->name }}
                            </button>
                        @endforeach
                    </div>
                @elseif ($editOrganizationSearch !== '')
                    <flux:text variant="subtle">No match found.</flux:text>
                @else
                    <flux:text variant="subtle">No organizations yet.</flux:text>
                @endif
            </flux:modal>
        @endcan
    @endif

</div>
