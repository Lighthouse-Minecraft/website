<?php

use App\Actions\CreateJournalEntry;
use App\Actions\ParseDollarAmount;
use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use App\Models\FinancialRestrictedFund;
use App\Models\FinancialTag;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    public string $entryType = 'income';

    // Common fields
    public string $date = '';

    public string $description = '';

    public string $amount = '';

    public string $reference = '';

    public string $status = 'draft';

    // Income-specific
    public string $donorEmail = '';

    public ?int $revenueAccountId = null;

    public ?int $bankAccountId = null;

    // Restricted fund (shared for income + expense)
    public ?int $restrictedFundId = null;

    // Expense-specific
    public ?int $expenseAccountId = null;

    public ?int $expenseBankAccountId = null;

    public ?int $vendorId = null;

    public string $vendorName = '';

    // Transfer-specific
    public ?int $fromAccountId = null;

    public ?int $toAccountId = null;

    // Tags (shared)
    public array $tagIds = [];

    public array $tagNames = [];

    // Preview state
    public bool $showPreview = false;

    public array $previewLines = [];

    public function mount(): void
    {
        $this->authorize('finance-record');
        $this->date = now()->format('Y-m-d');
    }

    public function getBankAccountsProperty()
    {
        return FinancialAccount::where('is_bank_account', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function getRevenueAccountsProperty()
    {
        return FinancialAccount::where('type', 'revenue')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function getExpenseAccountsProperty()
    {
        return FinancialAccount::where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function getActiveFundsProperty()
    {
        return FinancialRestrictedFund::where('is_active', true)->orderBy('name')->get();
    }

    public function getAllActiveAccountsProperty()
    {
        return FinancialAccount::where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function getSelectedTagsProperty(): array
    {
        if (empty($this->tagIds)) {
            return [];
        }

        return FinancialTag::whereIn('id', $this->tagIds)->get(['id', 'name', 'color'])->toArray();
    }

    #[\Livewire\Attributes\On('vendor-selected')]
    public function onVendorSelected(int $vendorId, string $vendorName): void
    {
        $this->vendorId = $vendorId;
        $this->vendorName = $vendorName;
    }

    #[\Livewire\Attributes\On('tag-selected')]
    public function onTagSelected(int $tagId, string $tagName, string $tagColor): void
    {
        if (! in_array($tagId, $this->tagIds)) {
            $this->tagIds[] = $tagId;
            $this->tagNames[] = $tagName;
        }
    }

    public function removeTag(int $tagId): void
    {
        $this->tagIds = array_values(array_filter($this->tagIds, fn ($id) => $id !== $tagId));
        $this->tagNames = [];
    }

    public function preview(): void
    {
        $this->validateForm();

        $amountCents = ParseDollarAmount::run($this->amount);

        $this->previewLines = $this->buildPreviewLines($amountCents);
        $this->showPreview = true;
    }

    public function cancelPreview(): void
    {
        $this->showPreview = false;
    }

    public function save(string $saveStatus = 'draft'): void
    {
        $this->authorize('finance-record');
        $this->validateForm();

        $amountCents = ParseDollarAmount::run($this->amount);

        try {
            $period = $this->resolvePeriod();
        } catch (\Exception) {
            return;
        }

        [$primaryAccountId, $bankAcctId] = $this->resolveAccountPair();

        CreateJournalEntry::run(
            user: auth()->user(),
            type: $this->entryType,
            periodId: $period->id,
            date: $this->date,
            description: $this->description,
            amountCents: $amountCents,
            primaryAccountId: $primaryAccountId,
            bankAccountId: $bankAcctId,
            status: $saveStatus,
            donorEmail: $this->entryType === 'income' ? ($this->donorEmail ?: null) : null,
            vendorId: $this->entryType === 'expense' ? $this->vendorId : null,
            restrictedFundId: in_array($this->entryType, ['income', 'expense']) ? $this->restrictedFundId : null,
            tagIds: $this->tagIds,
            reference: $this->reference ?: null,
        );

        $label = $saveStatus === 'posted' ? 'posted' : 'saved as draft';
        Flux::toast("Journal entry {$label} successfully.", 'Done', variant: 'success');

        $this->redirect(route('finance.journal.index'));
    }

    private function validateForm(): void
    {
        $rules = [
            'date' => 'required|date',
            'description' => 'required|string|max:500',
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{0,2})?$/'],
        ];

        if ($this->entryType === 'income') {
            $rules['revenueAccountId'] = 'required|integer|exists:financial_accounts,id';
            $rules['bankAccountId'] = 'required|integer|exists:financial_accounts,id';
        } elseif ($this->entryType === 'expense') {
            $rules['expenseAccountId'] = 'required|integer|exists:financial_accounts,id';
            $rules['expenseBankAccountId'] = 'required|integer|exists:financial_accounts,id';
        } elseif ($this->entryType === 'transfer') {
            $rules['fromAccountId'] = 'required|integer|exists:financial_accounts,id';
            $rules['toAccountId'] = 'required|integer|exists:financial_accounts,id';
        }

        $this->validate($rules, [
            'amount.regex' => 'Enter a valid dollar amount up to $999,999,999.99 (e.g. 10, 10.00).',
        ]);
    }

    private function resolveAccountPair(): array
    {
        return match ($this->entryType) {
            'income' => [$this->revenueAccountId, $this->bankAccountId],
            'expense' => [$this->expenseAccountId, $this->expenseBankAccountId],
            'transfer' => [$this->fromAccountId, $this->toAccountId],
        };
    }

    private function buildPreviewLines(int $amountCents): array
    {
        [$primaryAccountId, $bankAcctId] = $this->resolveAccountPair();

        [$debitId, $creditId] = match ($this->entryType) {
            'income' => [$bankAcctId, $primaryAccountId],
            'expense' => [$primaryAccountId, $bankAcctId],
            'transfer' => [$bankAcctId, $primaryAccountId],
        };

        $debitAccount = FinancialAccount::find($debitId);
        $creditAccount = FinancialAccount::find($creditId);

        $formatted = '$'.number_format($amountCents / 100, 2);

        return [
            ['side' => 'Debit',  'account' => $debitAccount?->name,  'amount' => $formatted, 'date' => $this->date],
            ['side' => 'Credit', 'account' => $creditAccount?->name, 'amount' => $formatted, 'date' => $this->date],
        ];
    }

    private function resolvePeriod(): \App\Models\FinancialPeriod
    {
        $date = \Carbon\Carbon::parse($this->date);

        $period = FinancialPeriod::whereRaw('DATE(start_date) <= ?', [$date->toDateString()])
            ->whereRaw('DATE(end_date) >= ?', [$date->toDateString()])
            ->first();

        if (! $period) {
            // Auto-generate periods if missing
            \App\Actions\GenerateFinancialPeriods::generateForCurrentFY();

            $period = FinancialPeriod::whereRaw('DATE(start_date) <= ?', [$date->toDateString()])
                ->whereRaw('DATE(end_date) >= ?', [$date->toDateString()])
                ->first();
        }

        if (! $period) {
            $this->addError('date', 'No fiscal period found for this date.');
            throw new \Exception('No fiscal period for date.');
        }

        if ($period->status === 'closed') {
            $this->addError('date', 'This period is closed and cannot accept new entries.');
            throw new \Exception('Period is closed.');
        }

        return $period;
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">New Journal Entry</flux:heading>
            <flux:text variant="subtle">Record an income, expense, or transfer transaction.</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('finance.journal.index') }}" wire:navigate>
            Back to Journal
        </flux:button>
    </div>

    {{-- Entry type tabs --}}
    @if (! $showPreview)
        <flux:card>
            <div class="flex gap-2 mb-6">
                @foreach (['income' => 'Income', 'expense' => 'Expense', 'transfer' => 'Transfer'] as $type => $label)
                    <button
                        wire:click="$set('entryType', '{{ $type }}')"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                            {{ $entryType === $type
                                ? 'bg-blue-600 text-white'
                                : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="space-y-4">
                {{-- Common fields --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Date</flux:label>
                        <flux:input type="date" wire:model="date" />
                        <flux:error name="date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Amount ($)</flux:label>
                        <flux:input wire:model="amount" placeholder="0.00" />
                        <flux:error name="amount" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:input wire:model="description" placeholder="Brief description of this transaction" />
                    <flux:error name="description" />
                </flux:field>

                <flux:field>
                    <flux:label>Reference <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                    <flux:input wire:model="reference" placeholder="Check number, receipt, etc." />
                </flux:field>

                {{-- Income-specific fields --}}
                @if ($entryType === 'income')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Revenue Account</flux:label>
                            <flux:select wire:model="revenueAccountId">
                                <flux:select.option value="">Select account…</flux:select.option>
                                @foreach ($this->revenueAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">
                                        {{ $account->code }} — {{ $account->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="revenueAccountId" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Deposited To</flux:label>
                            <flux:select wire:model="bankAccountId">
                                <flux:select.option value="">Select bank account…</flux:select.option>
                                @foreach ($this->bankAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">
                                        {{ $account->code }} — {{ $account->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="bankAccountId" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>Donor Email <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                            <flux:input type="email" wire:model="donorEmail" placeholder="donor@example.com" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Restricted Fund <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                            <flux:select wire:model="restrictedFundId">
                                <flux:select.option value="">None (unrestricted)</flux:select.option>
                                @foreach ($this->activeFunds as $fund)
                                    <flux:select.option value="{{ $fund->id }}">{{ $fund->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Tags <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($this->selectedTags as $tag)
                                    <flux:badge wire:key="income-tag-{{ $tag['id'] }}" color="{{ $tag['color'] }}" size="sm">
                                        {{ $tag['name'] }}
                                        <button wire:click="removeTag({{ $tag['id'] }})" class="ml-1 hover:opacity-70">×</button>
                                    </flux:badge>
                                @endforeach
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    wire:click="$dispatch('open-tag-search', { selectedTagIds: {{ json_encode($tagIds) }} })"
                                >
                                    + Add Tag
                                </flux:button>
                            </div>
                        </flux:field>
                    </div>
                @endif

                {{-- Expense-specific fields --}}
                @if ($entryType === 'expense')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Expense Account</flux:label>
                            <flux:select wire:model="expenseAccountId">
                                <flux:select.option value="">Select account…</flux:select.option>
                                @foreach ($this->expenseAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">
                                        {{ $account->code }} — {{ $account->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="expenseAccountId" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Paid From</flux:label>
                            <flux:select wire:model="expenseBankAccountId">
                                <flux:select.option value="">Select bank account…</flux:select.option>
                                @foreach ($this->bankAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">
                                        {{ $account->code }} — {{ $account->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="expenseBankAccountId" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>Vendor <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                            <div class="flex items-center gap-2">
                                @if ($vendorId)
                                    <flux:badge color="blue">{{ $vendorName }}</flux:badge>
                                    <flux:button variant="ghost" size="sm" wire:click="$set('vendorId', null)">Clear</flux:button>
                                @else
                                    <flux:button variant="outline" size="sm" wire:click="$dispatch('open-vendor-search')">
                                        Select Vendor
                                    </flux:button>
                                @endif
                            </div>
                        </flux:field>

                        <flux:field>
                            <flux:label>Restricted Fund <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                            <flux:select wire:model="restrictedFundId">
                                <flux:select.option value="">None</flux:select.option>
                                @foreach ($this->activeFunds as $fund)
                                    <flux:select.option value="{{ $fund->id }}">{{ $fund->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Tags <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($this->selectedTags as $tag)
                                    <flux:badge wire:key="expense-tag-{{ $tag['id'] }}" color="{{ $tag['color'] }}" size="sm">
                                        {{ $tag['name'] }}
                                        <button wire:click="removeTag({{ $tag['id'] }})" class="ml-1 hover:opacity-70">×</button>
                                    </flux:badge>
                                @endforeach
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    wire:click="$dispatch('open-tag-search', { selectedTagIds: {{ json_encode($tagIds) }} })"
                                >
                                    + Add Tag
                                </flux:button>
                            </div>
                        </flux:field>
                    </div>
                @endif

                {{-- Transfer-specific fields --}}
                @if ($entryType === 'transfer')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>From Account</flux:label>
                            <flux:select wire:model="fromAccountId">
                                <flux:select.option value="">Select account…</flux:select.option>
                                @foreach ($this->bankAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">
                                        {{ $account->code }} — {{ $account->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="fromAccountId" />
                        </flux:field>

                        <flux:field>
                            <flux:label>To Account</flux:label>
                            <flux:select wire:model="toAccountId">
                                <flux:select.option value="">Select account…</flux:select.option>
                                @foreach ($this->bankAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">
                                        {{ $account->code }} — {{ $account->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="toAccountId" />
                        </flux:field>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="save('draft')">Save as Draft</flux:button>
                <flux:button variant="primary" wire:click="preview">Preview & Post</flux:button>
            </div>
        </flux:card>
    @endif

    {{-- Preview panel --}}
    @if ($showPreview)
        <flux:card>
            <flux:heading size="md" class="mb-4">Preview Debit/Credit Lines</flux:heading>
            <flux:text variant="subtle" class="mb-4">
                Verify the accounting lines below before posting. Once posted, this entry cannot be edited.
            </flux:text>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Side</flux:table.column>
                    <flux:table.column>Account</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Amount</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($previewLines as $line)
                        <flux:table.row wire:key="preview-line-{{ $loop->index }}">
                            <flux:table.cell>
                                <flux:badge color="{{ $line['side'] === 'Debit' ? 'blue' : 'green' }}" size="sm">
                                    {{ $line['side'] }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $line['account'] }}</flux:table.cell>
                            <flux:table.cell>{{ \Carbon\Carbon::parse($line['date'])->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell class="font-mono">{{ $line['amount'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="cancelPreview">Back to Form</flux:button>
                <flux:button variant="primary" wire:click="save('posted')">Post Entry</flux:button>
            </div>
        </flux:card>
    @endif

    @livewire('finance.vendor-search-modal')
    @livewire('finance.tag-search-modal')
</div>
