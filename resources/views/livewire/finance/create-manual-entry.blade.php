<?php

use App\Actions\ParseDollarAmount;
use App\Actions\PostJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    public string $date = '';

    public string $description = '';

    public string $reference = '';

    // Lines: array of ['account_id' => null, 'side' => 'debit'|'credit', 'amount' => '', 'memo' => '']
    public array $lines = [];

    public function mount(): void
    {
        $this->authorize('finance-record');
        $this->date = now()->format('Y-m-d');

        // Start with one debit and one credit line
        $this->lines = [
            ['account_id' => null, 'side' => 'debit',  'amount' => '', 'memo' => ''],
            ['account_id' => null, 'side' => 'credit', 'amount' => '', 'memo' => ''],
        ];
    }

    public function getAccountsProperty()
    {
        return FinancialAccount::where('is_active', true)->orderBy('code')->get();
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

    public function addLine(string $side): void
    {
        $this->lines[] = ['account_id' => null, 'side' => $side, 'amount' => '', 'memo' => ''];
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        $this->lines = array_values($this->lines);
    }

    public function save(string $saveStatus = 'draft'): void
    {
        $this->authorize('finance-record');

        $this->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:financial_accounts,id',
            'lines.*.amount' => ['required', 'regex:/^\d{1,9}(\.\d{0,2})?$/'],
        ], [
            'lines.*.account_id.required' => 'Each line must have an account selected.',
            'lines.*.amount.required' => 'Each line must have an amount.',
            'lines.*.amount.regex' => 'Enter a valid dollar amount up to $999,999,999.99 (e.g. 10, 10.00).',
        ]);

        if ($saveStatus === 'posted' && ! $this->isBalanced) {
            $this->addError('lines', 'Debits must equal credits before posting.');

            return;
        }

        $period = $this->resolvePeriod();

        if (! $period) {
            return;
        }

        $entry = FinancialJournalEntry::create([
            'period_id' => $period->id,
            'date' => $this->date,
            'description' => $this->description,
            'reference' => $this->reference ?: null,
            'entry_type' => 'journal',
            'status' => 'draft',
            'created_by_id' => auth()->id(),
        ]);

        foreach ($this->lines as $line) {
            $cents = ParseDollarAmount::run($line['amount']);
            $entry->lines()->create([
                'account_id' => (int) $line['account_id'],
                'debit' => $line['side'] === 'debit' ? $cents : 0,
                'credit' => $line['side'] === 'credit' ? $cents : 0,
                'memo' => $line['memo'] ?: null,
            ]);
        }

        if ($saveStatus === 'posted') {
            PostJournalEntry::run(auth()->user(), $entry);
        }

        $label = $saveStatus === 'posted' ? 'posted' : 'saved as draft';
        Flux::toast("Manual journal entry {$label} successfully.", 'Done', variant: 'success');

        $this->redirect(route('finance.journal.index'));
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

    private function resolvePeriod(): ?\App\Models\FinancialPeriod
    {
        $date = \Carbon\Carbon::parse($this->date);

        $period = FinancialPeriod::whereRaw('DATE(start_date) <= ?', [$date->toDateString()])
            ->whereRaw('DATE(end_date) >= ?', [$date->toDateString()])
            ->first();

        if (! $period) {
            \App\Actions\GenerateFinancialPeriods::generateForCurrentFY();
            $period = FinancialPeriod::whereRaw('DATE(start_date) <= ?', [$date->toDateString()])
                ->whereRaw('DATE(end_date) >= ?', [$date->toDateString()])
                ->first();
        }

        if (! $period) {
            $this->addError('date', 'No fiscal period found for this date.');

            return null;
        }

        if ($period->status === 'closed') {
            $this->addError('date', 'This period is closed and cannot accept new entries.');

            return null;
        }

        return $period;
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Manual Journal Entry</flux:heading>
            <flux:text variant="subtle">Enter explicit debit and credit lines for complex or unusual transactions.</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('finance.journal.index') }}" wire:navigate>
            Back to Journal
        </flux:button>
    </div>

    <flux:card>
        <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Date</flux:label>
                    <flux:input type="date" wire:model="date" />
                    <flux:error name="date" />
                </flux:field>

                <flux:field>
                    <flux:label>Reference <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                    <flux:input wire:model="reference" placeholder="Check number, receipt, etc." />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:input wire:model="description" placeholder="Brief description of this transaction" />
                <flux:error name="description" />
            </flux:field>

            {{-- Lines table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="text-left py-2 px-2 text-zinc-500 font-medium">Side</th>
                            <th class="text-left py-2 px-2 text-zinc-500 font-medium">Account</th>
                            <th class="text-right py-2 px-2 text-zinc-500 font-medium">Amount ($)</th>
                            <th class="text-left py-2 px-2 text-zinc-500 font-medium">Memo</th>
                            <th class="py-2 px-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800" wire:key="line-{{ $index }}">
                                <td class="py-1.5 px-2">
                                    <select
                                        wire:model.live="lines.{{ $index }}.side"
                                        class="text-sm border border-zinc-200 dark:border-zinc-700 rounded px-2 py-1 bg-white dark:bg-zinc-900
                                            {{ $line['side'] === 'debit' ? 'text-blue-600 dark:text-blue-400' : 'text-green-600 dark:text-green-400' }}"
                                    >
                                        <option value="debit">Debit</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </td>

                                <td class="py-1.5 px-2">
                                    <select
                                        wire:model="lines.{{ $index }}.account_id"
                                        class="w-full text-sm border border-zinc-200 dark:border-zinc-700 rounded px-2 py-1 bg-white dark:bg-zinc-900"
                                    >
                                        <option value="">Select account…</option>
                                        @foreach ($this->accounts as $account)
                                            <option value="{{ $account->id }}">
                                                {{ $account->code }} — {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <flux:error name="lines.{{ $index }}.account_id" />
                                </td>

                                <td class="py-1.5 px-2">
                                    <input
                                        type="text"
                                        wire:model.live="lines.{{ $index }}.amount"
                                        placeholder="0.00"
                                        class="w-24 text-right text-sm border border-zinc-200 dark:border-zinc-700 rounded px-2 py-1 bg-white dark:bg-zinc-900"
                                    />
                                    <flux:error name="lines.{{ $index }}.amount" />
                                </td>

                                <td class="py-1.5 px-2">
                                    <input
                                        type="text"
                                        wire:model="lines.{{ $index }}.memo"
                                        placeholder="Optional memo"
                                        class="w-full text-sm border border-zinc-200 dark:border-zinc-700 rounded px-2 py-1 bg-white dark:bg-zinc-900"
                                    />
                                </td>

                                <td class="py-1.5 px-2">
                                    @if (count($lines) > 2)
                                        <flux:button
                                            variant="ghost"
                                            icon="trash"
                                            size="sm"
                                            wire:click="removeLine({{ $index }})"
                                        />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <flux:error name="lines" />
            </div>

            <div class="flex gap-3">
                <flux:button variant="outline" size="sm" wire:click="addLine('debit')">+ Add Debit Line</flux:button>
                <flux:button variant="outline" size="sm" wire:click="addLine('credit')">+ Add Credit Line</flux:button>
            </div>

            {{-- Running totals --}}
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 bg-zinc-50 dark:bg-zinc-800/50">
                <div class="flex gap-6 text-sm">
                    <div>
                        <span class="text-zinc-500">Total Debits:</span>
                        <span class="font-mono font-medium ml-2 text-blue-600 dark:text-blue-400">
                            ${{ number_format($this->totalDebits / 100, 2) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-zinc-500">Total Credits:</span>
                        <span class="font-mono font-medium ml-2 text-green-600 dark:text-green-400">
                            ${{ number_format($this->totalCredits / 100, 2) }}
                        </span>
                    </div>
                    @if ($this->difference > 0)
                        <div>
                            <span class="text-zinc-500">Difference:</span>
                            <span class="font-mono font-medium ml-2 text-red-600 dark:text-red-400">
                                ${{ number_format($this->difference / 100, 2) }}
                            </span>
                        </div>
                    @else
                        <div class="text-green-600 dark:text-green-400 font-medium">✓ Balanced</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" wire:click="save('draft')">Save as Draft</flux:button>
            <flux:button
                variant="primary"
                wire:click="save('posted')"
                :disabled="! $this->isBalanced"
            >
                Post Entry
            </flux:button>
        </div>
    </flux:card>
</div>
