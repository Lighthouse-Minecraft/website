<?php

use App\Actions\RecordFinancialTransaction;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTag;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $type = 'expense';
    public string $accountId = '';
    public string $amount = '';
    public string $transactedAt = '';
    public string $categoryId = '';
    public string $subcategoryId = '';
    public string $notes = '';
    public array $selectedTagIds = [];

    public function mount(): void
    {
        $this->transactedAt = now()->format('Y-m-d');
    }

    public function accounts(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialAccount::where('is_archived', false)->orderBy('name')->get();
    }

    public function topLevelCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialCategory::whereNull('parent_id')
            ->where('type', $this->type)
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

    public function tags(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialTag::where('is_archived', false)->orderBy('name')->get();
    }

    public function updatedType(): void
    {
        $this->reset(['categoryId', 'subcategoryId']);
    }

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = '';
    }

    public function submitTransaction(): void
    {
        $this->authorize('financials-treasurer');

        $this->validate([
            'type' => 'required|in:income,expense',
            'accountId' => 'required|integer|exists:financial_accounts,id',
            'amount' => 'required|integer|min:1',
            'transactedAt' => 'required|date',
            'categoryId' => 'required|integer|exists:financial_categories,id',
            'subcategoryId' => 'nullable|integer|exists:financial_categories,id',
            'notes' => 'nullable|string|max:1000',
            'selectedTagIds' => 'array',
            'selectedTagIds.*' => 'integer|exists:financial_tags,id',
        ]);

        $effectiveCategoryId = $this->subcategoryId !== '' ? (int) $this->subcategoryId : (int) $this->categoryId;

        RecordFinancialTransaction::run(
            auth()->user(),
            (int) $this->accountId,
            $this->type,
            (int) $this->amount,
            $this->transactedAt,
            $effectiveCategoryId,
            $this->notes ?: null,
            $this->selectedTagIds,
        );

        Flux::toast('Transaction recorded.', 'Success', variant: 'success');
        $this->reset(['accountId', 'amount', 'categoryId', 'subcategoryId', 'notes', 'selectedTagIds']);
        $this->transactedAt = now()->format('Y-m-d');
    }
}; ?>

<div class="space-y-8">

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

    {{-- Transaction Entry Form (treasurer only) --}}
    @can('financials-treasurer')
        <flux:card class="space-y-5">
            <flux:heading size="lg">Record Transaction</flux:heading>

            <form wire:submit.prevent="submitTransaction" class="space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Type <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="type">
                            <flux:select.option value="expense">Expense</flux:select.option>
                            <flux:select.option value="income">Income</flux:select.option>
                        </flux:select>
                        <flux:error name="type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Account <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model="accountId">
                            <flux:select.option value="">— Select Account —</flux:select.option>
                            @foreach ($this->accounts() as $account)
                                <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="accountId" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Amount (cents) <span class="text-red-500">*</span></flux:label>
                        <flux:description>e.g. 1000 = $10.00</flux:description>
                        <flux:input wire:model="amount" type="number" min="1" placeholder="1000" />
                        <flux:error name="amount" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Date <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="transactedAt" type="date" />
                        <flux:error name="transactedAt" />
                    </flux:field>
                </div>

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

                @if ($this->tags()->isNotEmpty())
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

                <flux:button type="submit" variant="primary" icon="plus">Record Transaction</flux:button>
            </form>
        </flux:card>
    @endcan

</div>
