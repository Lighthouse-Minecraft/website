<?php

use App\Actions\ParseDollarAmount;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\FinancialRestrictedFund;
use App\Models\FinancialTag;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    public ?FinancialJournalEntry $entry = null;

    public string $entryType = '';

    // Common fields
    public string $date = '';

    public string $description = '';

    public string $reference = '';

    // Guided entry fields
    public string $amount = '';

    public ?int $revenueAccountId = null;

    public ?int $bankAccountId = null;

    public ?int $restrictedFundId = null;

    public string $donorEmail = '';

    public ?int $expenseAccountId = null;

    public ?int $expenseBankAccountId = null;

    public ?int $vendorId = null;

    public string $vendorName = '';

    public ?int $fromAccountId = null;

    public ?int $toAccountId = null;

    public array $tagIds = [];

    // Manual entry lines
    public array $lines = [];

    public function mount(int $entryId): void
    {
        $this->authorize('finance-record');

        $entry = FinancialJournalEntry::with(['lines.account', 'tags', 'vendor'])->findOrFail($entryId);

        if ($entry->status !== 'draft') {
            Flux::toast('Only draft entries can be edited.', 'Cannot Edit', variant: 'danger');
            $this->redirect(route('finance.journal.index'));

            return;
        }

        $this->entry = $entry;
        $this->entryType = $entry->entry_type;
        $this->date = $entry->date->format('Y-m-d');
        $this->description = $entry->description;
        $this->reference = $entry->reference ?? '';

        if (in_array($this->entryType, ['income', 'expense', 'transfer'])) {
            $debitLine = $entry->lines->firstWhere('debit', '>', 0);
            $creditLine = $entry->lines->firstWhere('credit', '>', 0);
            $amountCents = $debitLine?->debit ?? $creditLine?->credit ?? 0;
            $this->amount = number_format($amountCents / 100, 2);

            if ($this->entryType === 'income') {
                $this->bankAccountId = $debitLine?->account_id;
                $this->revenueAccountId = $creditLine?->account_id;
                $this->donorEmail = $entry->donor_email ?? '';
                $this->restrictedFundId = $entry->restricted_fund_id;
            } elseif ($this->entryType === 'expense') {
                $this->expenseAccountId = $debitLine?->account_id;
                $this->expenseBankAccountId = $creditLine?->account_id;
                $this->vendorId = $entry->vendor_id;
                $this->vendorName = $entry->vendor?->name ?? '';
                $this->restrictedFundId = $entry->restricted_fund_id;
            } elseif ($this->entryType === 'transfer') {
                $this->toAccountId = $debitLine?->account_id;
                $this->fromAccountId = $creditLine?->account_id;
            }

            $this->tagIds = $entry->tags->pluck('id')->toArray();
        } elseif ($this->entryType === 'journal') {
            $this->lines = $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'side' => $line->debit > 0 ? 'debit' : 'credit',
                'amount' => number_format(($line->debit ?: $line->credit) / 100, 2),
                'memo' => $line->memo ?? '',
            ])->toArray();
        }
    }

    public function getBankAccountsProperty()
    {
        return FinancialAccount::where('is_bank_account', true)->where('is_active', true)->orderBy('code')->get();
    }

    public function getRevenueAccountsProperty()
    {
        return FinancialAccount::where('type', 'revenue')->where('is_active', true)->orderBy('code')->get();
    }

    public function getExpenseAccountsProperty()
    {
        return FinancialAccount::where('type', 'expense')->where('is_active', true)->orderBy('code')->get();
    }

    public function getActiveFundsProperty()
    {
        return FinancialRestrictedFund::where('is_active', true)->orderBy('name')->get();
    }

    public function getAllAccountsProperty()
    {
        return FinancialAccount::where('is_active', true)->orderBy('code')->get();
    }

    public function getSelectedTagsProperty(): array
    {
        if (empty($this->tagIds)) {
            return [];
        }

        return FinancialTag::whereIn('id', $this->tagIds)->get(['id', 'name', 'color'])->toArray();
    }

    public function getTotalDebitsProperty(): int
    {
        return $this->sumSide('debit');
    }

    public function getTotalCreditsProperty(): int
    {
        return $this->sumSide('credit');
    }

    public function getDifferenceProperty(): int
    {
        return abs($this->totalDebits - $this->totalCredits);
    }

    public function getIsBalancedProperty(): bool
    {
        return $this->totalDebits > 0
            && $this->totalCredits > 0
            && $this->totalDebits === $this->totalCredits;
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
        }
    }

    public function removeTag(int $tagId): void
    {
        $this->tagIds = array_values(array_filter($this->tagIds, fn ($id) => $id !== $tagId));
    }

    public function addLine(string $side): void
    {
        $this->lines[] = ['account_id' => null, 'side' => $side, 'amount' => '', 'memo' => ''];
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->authorize('finance-record');

        if (! $this->entry || $this->entry->status !== 'draft') {
            Flux::toast('Only draft entries can be edited.', 'Cannot Edit', variant: 'danger');

            return;
        }

        if (in_array($this->entryType, ['income', 'expense', 'transfer'])) {
            $this->saveGuidedEntry();
        } elseif ($this->entryType === 'journal') {
            $this->saveManualEntry();
        }
    }

    private function saveGuidedEntry(): void
    {
        $rules = [
            'date' => 'required|date',
            'description' => 'required|string|max:500',
            'amount' => ['required', 'regex:/^\d+(\.\d{0,2})?$/'],
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
            'amount.regex' => 'Enter a valid dollar amount (e.g. 10, 10.00).',
        ]);

        try {
            $period = $this->resolvePeriod();
        } catch (\Exception) {
            return;
        }

        $amountCents = ParseDollarAmount::run($this->amount);

        [$debitAccountId, $creditAccountId] = match ($this->entryType) {
            'income' => [$this->bankAccountId, $this->revenueAccountId],
            'expense' => [$this->expenseAccountId, $this->expenseBankAccountId],
            'transfer' => [$this->toAccountId, $this->fromAccountId],
        };

        $this->entry->update([
            'period_id' => $period->id,
            'date' => $this->date,
            'description' => $this->description,
            'reference' => $this->reference ?: null,
            'donor_email' => $this->entryType === 'income' ? ($this->donorEmail ?: null) : null,
            'vendor_id' => $this->entryType === 'expense' ? $this->vendorId : null,
            'restricted_fund_id' => in_array($this->entryType, ['income', 'expense']) ? $this->restrictedFundId : null,
        ]);

        $this->entry->lines()->delete();
        $this->entry->lines()->create(['account_id' => $debitAccountId, 'debit' => $amountCents, 'credit' => 0]);
        $this->entry->lines()->create(['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $amountCents]);
        $this->entry->tags()->sync($this->tagIds);

        Flux::toast('Draft entry updated.', 'Done', variant: 'success');
        $this->redirect(route('finance.journal.index'));
    }

    private function saveManualEntry(): void
    {
        $this->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:financial_accounts,id',
            'lines.*.amount' => ['required', 'regex:/^\d+(\.\d{0,2})?$/'],
        ], [
            'lines.*.account_id.required' => 'Each line must have an account selected.',
            'lines.*.amount.required' => 'Each line must have an amount.',
            'lines.*.amount.regex' => 'Enter a valid dollar amount (e.g. 10, 10.00).',
        ]);

        try {
            $period = $this->resolvePeriod();
        } catch (\Exception) {
            return;
        }

        $this->entry->update([
            'period_id' => $period->id,
            'date' => $this->date,
            'description' => $this->description,
            'reference' => $this->reference ?: null,
        ]);

        $this->entry->lines()->delete();

        foreach ($this->lines as $line) {
            $cents = ParseDollarAmount::run($line['amount']);
            $this->entry->lines()->create([
                'account_id' => (int) $line['account_id'],
                'debit' => $line['side'] === 'debit' ? $cents : 0,
                'credit' => $line['side'] === 'credit' ? $cents : 0,
                'memo' => $line['memo'] ?: null,
            ]);
        }

        Flux::toast('Draft entry updated.', 'Done', variant: 'success');
        $this->redirect(route('finance.journal.index'));
    }

    public function delete(): void
    {
        $this->authorize('finance-record');

        if (! $this->entry || $this->entry->status !== 'draft') {
            return;
        }

        $this->entry->delete();

        Flux::toast('Draft entry deleted.', 'Done', variant: 'success');
        $this->redirect(route('finance.journal.index'));
    }

    private function resolvePeriod(): FinancialPeriod
    {
        $date = \Carbon\Carbon::parse($this->date);

        $period = FinancialPeriod::where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if (! $period) {
            \App\Actions\GenerateFinancialPeriods::generateForCurrentFY();

            $period = FinancialPeriod::where('start_date', '<=', $date->toDateString())
                ->where('end_date', '>=', $date->toDateString())
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

    private function sumSide(string $side): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            if ($line['side'] !== $side) {
                continue;
            }

            try {
                $total += ParseDollarAmount::run($line['amount'] ?: '0');
            } catch (\InvalidArgumentException) {
                // Invalid amount, skip
            }
        }

        return $total;
    }
}; ?>

<div class="space-y-6">
    @include('livewire.finance.partials.nav')

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Draft Entry</flux:heading>
            <flux:text variant="subtle">
                {{ ucfirst($entryType) }} entry
                @if ($entry)
                    — #{{ $entry->id }}
                @endif
            </flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('finance.journal.index') }}" wire:navigate>
            Back to Journal
        </flux:button>
    </div>

    @if ($entry)
        <flux:card>
            <div class="space-y-4">

                {{-- Common fields --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Date</flux:label>
                        <flux:input type="date" wire:model="date" />
                        <flux:error name="date" />
                    </flux:field>

                    @if (in_array($entryType, ['income', 'expense', 'transfer']))
                        <flux:field>
                            <flux:label>Amount ($)</flux:label>
                            <flux:input wire:model="amount" placeholder="0.00" />
                            <flux:error name="amount" />
                        </flux:field>
                    @endif
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

                {{-- Income fields --}}
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
                @endif

                {{-- Expense fields --}}
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
                @endif

                {{-- Transfer fields --}}
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

                {{-- Tags (guided entries except transfer) --}}
                @if (in_array($entryType, ['income', 'expense']))
                    <flux:field>
                        <flux:label>Tags <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach ($this->selectedTags as $tag)
                                <flux:badge color="{{ $tag['color'] }}" size="sm">
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
                @endif

                {{-- Manual entry lines --}}
                @if ($entryType === 'journal')
                    <div class="mt-4">
                        <flux:error name="lines" />

                        <div class="space-y-2">
                            @foreach ($lines as $i => $line)
                                <div wire:key="line-{{ $i }}" class="flex items-center gap-2">
                                    <flux:select wire:model="lines.{{ $i }}.account_id" class="flex-1">
                                        <flux:select.option value="">Select account…</flux:select.option>
                                        @foreach ($this->allAccounts as $account)
                                            <flux:select.option value="{{ $account->id }}">
                                                {{ $account->code }} — {{ $account->name }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    <flux:select wire:model="lines.{{ $i }}.side" class="w-28">
                                        <flux:select.option value="debit">Debit</flux:select.option>
                                        <flux:select.option value="credit">Credit</flux:select.option>
                                    </flux:select>

                                    <flux:input wire:model="lines.{{ $i }}.amount" placeholder="0.00" class="w-28" />

                                    <flux:input wire:model="lines.{{ $i }}.memo" placeholder="Memo (opt)" class="flex-1" />

                                    @if (count($lines) > 2)
                                        <flux:button variant="ghost" size="sm" wire:click="removeLine({{ $i }})">×</flux:button>
                                    @endif
                                </div>
                                <flux:error name="lines.{{ $i }}.account_id" />
                                <flux:error name="lines.{{ $i }}.amount" />
                            @endforeach
                        </div>

                        <div class="flex gap-2 mt-3">
                            <flux:button variant="ghost" size="sm" wire:click="addLine('debit')">+ Debit Line</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="addLine('credit')">+ Credit Line</flux:button>
                        </div>

                        {{-- Balance summary --}}
                        <div class="mt-4 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 text-sm">
                            <div class="flex justify-between">
                                <span>Total Debits</span>
                                <span class="font-mono">${{ number_format($this->totalDebits / 100, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Credits</span>
                                <span class="font-mono">${{ number_format($this->totalCredits / 100, 2) }}</span>
                            </div>
                            @if ($this->difference > 0)
                                <div class="flex justify-between text-red-600 dark:text-red-400 font-medium mt-1">
                                    <span>Difference</span>
                                    <span class="font-mono">${{ number_format($this->difference / 100, 2) }}</span>
                                </div>
                            @else
                                <div class="flex justify-between text-green-600 dark:text-green-400 font-medium mt-1">
                                    <span>Balanced</span>
                                    <span>✓</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

            </div>

            <div class="flex items-center justify-between mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:modal.trigger name="confirm-delete-entry">
                    <flux:button variant="ghost" class="text-red-600 dark:text-red-400 hover:text-red-700">
                        Delete Draft
                    </flux:button>
                </flux:modal.trigger>

                <flux:button variant="primary" wire:click="save">Save Changes</flux:button>
            </div>
        </flux:card>
    @endif

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-delete-entry" class="max-w-sm">
        <flux:heading size="lg" class="mb-2">Delete This Draft?</flux:heading>
        <flux:text class="mb-4">This draft entry will be permanently deleted. This cannot be undone.</flux:text>
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">Delete Draft</flux:button>
        </div>
    </flux:modal>

    @if (in_array($entryType, ['income', 'expense']))
        @livewire('finance.vendor-search-modal')
        @livewire('finance.tag-search-modal')
    @endif
</div>
