<?php

use App\Actions\CompleteReconciliation;
use App\Actions\ParseDollarAmount;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntryLine;
use App\Models\FinancialPeriod;
use App\Models\FinancialReconciliation;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {

    public FinancialAccount $account;
    public FinancialPeriod $period;
    public FinancialReconciliation $reconciliation;

    public string $statementBalance = '';

    public function mount(int $accountId, int $periodId): void
    {
        $this->authorize('finance-record');

        $this->account = FinancialAccount::findOrFail($accountId);
        $this->period  = FinancialPeriod::findOrFail($periodId);

        if (! $this->account->is_bank_account) {
            abort(403, 'This account is not a bank account.');
        }

        $this->reconciliation = FinancialReconciliation::firstOrCreate(
            ['account_id' => $this->account->id, 'period_id' => $this->period->id],
            [
                'statement_date'           => $this->period->end_date->toDateString(),
                'statement_ending_balance' => 0,
                'status'                   => 'in_progress',
            ]
        );

        if ($this->reconciliation->statement_ending_balance > 0) {
            $this->statementBalance = number_format($this->reconciliation->statement_ending_balance / 100, 2);
        }
    }

    public function getUnclearedLinesProperty()
    {
        $clearedIds = $this->reconciliation->lines()->pluck('journal_entry_line_id');

        return FinancialJournalEntryLine::where('account_id', $this->account->id)
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('period_id', $this->period->id)
                ->where('status', 'posted')
            )
            ->whereNotIn('id', $clearedIds)
            ->with(['journalEntry'])
            ->orderBy('id')
            ->get();
    }

    public function getClearedLinesProperty()
    {
        return $this->reconciliation->lines()
            ->with(['journalEntryLine.journalEntry'])
            ->get();
    }

    public function getClearedBalanceProperty(): int
    {
        if ($this->clearedLines->isEmpty()) {
            return 0;
        }

        return (int) $this->clearedLines->sum(fn ($rl) =>
            ($rl->journalEntryLine->debit ?? 0) - ($rl->journalEntryLine->credit ?? 0)
        );
    }

    public function getOpeningBalanceProperty(): int
    {
        $prior = FinancialReconciliation::findPriorCompleted(
            $this->account->id,
            $this->period->start_date->toDateString()
        );

        return $prior?->statement_ending_balance ?? 0;
    }

    public function getStatementBalanceCentsProperty(): int
    {
        try {
            return ParseDollarAmount::run($this->statementBalance ?: '0');
        } catch (\InvalidArgumentException) {
            return 0;
        }
    }

    public function getDifferenceProperty(): int
    {
        return $this->statementBalanceCents - ($this->openingBalance + $this->clearedBalance);
    }

    public function getIsBalancedProperty(): bool
    {
        // Allow $0.00 reconciliation (e.g. savings account with no activity):
        // require the statement balance field to have been explicitly entered (non-empty)
        // and the difference to be zero.
        return $this->statementBalance !== '' && $this->difference === 0;
    }

    public function markCleared(int $lineId): void
    {
        $this->authorize('finance-record');

        if ($this->reconciliation->status === 'completed') {
            return;
        }

        $this->reconciliation->lines()->firstOrCreate([
            'journal_entry_line_id' => $lineId,
        ], [
            'cleared_at' => now(),
        ]);
    }

    public function unmarkCleared(int $reconciliationLineId): void
    {
        $this->authorize('finance-record');

        if ($this->reconciliation->status === 'completed') {
            return;
        }

        $this->reconciliation->lines()->where('id', $reconciliationLineId)->delete();
    }

    public function updateStatementBalance(): void
    {
        $this->authorize('finance-record');

        try {
            $cents = ParseDollarAmount::run($this->statementBalance ?: '0');
        } catch (\InvalidArgumentException) {
            $this->addError('statementBalance', 'Enter a valid dollar amount.');

            return;
        }

        $this->reconciliation->update(['statement_ending_balance' => $cents]);
    }

    public function complete(): void
    {
        $this->authorize('finance-record');

        $this->updateStatementBalance();

        try {
            CompleteReconciliation::run($this->reconciliation->fresh(), auth()->user());
            $this->reconciliation = $this->reconciliation->fresh();
            Flux::toast('Reconciliation completed successfully.', 'Done', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Bank Reconciliation</flux:heading>
            <flux:text variant="subtle">
                {{ $account->name }} — {{ $period->name }}
            </flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" href="{{ route('finance.periods.index') }}" wire:navigate>
                Back to Periods
            </flux:button>
            @if ($reconciliation->status === 'completed')
                <flux:badge color="green">Completed</flux:badge>
            @endif
        </div>
    </div>

    {{-- Statement balance + summary --}}
    <flux:card>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="text-center">
                <p class="text-xs text-zinc-500 mb-1">Opening Balance</p>
                <p class="text-lg font-mono font-semibold dark:text-zinc-100">${{ number_format($this->openingBalance / 100, 2) }}</p>
            </div>

            <div class="text-center">
                <p class="text-xs text-zinc-500 mb-1">Cleared Balance</p>
                <p class="text-lg font-mono font-semibold">${{ number_format($this->clearedBalance / 100, 2) }}</p>
            </div>

            <div class="text-center">
                <p class="text-xs text-zinc-500 mb-1">Statement Balance</p>
                <p class="text-lg font-mono font-semibold">${{ number_format($this->statementBalanceCents / 100, 2) }}</p>
            </div>

            <div class="text-center">
                <p class="text-xs text-zinc-500 mb-1">Difference</p>
                <p class="text-lg font-mono font-semibold {{ $this->difference === 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    ${{ number_format(abs($this->difference) / 100, 2) }}
                    @if ($this->difference !== 0)
                        ({{ $this->difference > 0 ? '+' : '-' }})
                    @else
                        ✓
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-end justify-between mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:field>
                <flux:label>Statement Ending Balance ($)</flux:label>
                @if ($reconciliation->status === 'completed')
                    <p class="text-sm font-mono font-medium">${{ number_format($reconciliation->statement_ending_balance / 100, 2) }}</p>
                @else
                    <flux:input
                        wire:model.live="statementBalance"
                        wire:change="updateStatementBalance"
                        placeholder="0.00"
                        class="w-40"
                    />
                    <flux:error name="statementBalance" />
                @endif
            </flux:field>

            @if ($reconciliation->status !== 'completed')
                <flux:button
                    variant="primary"
                    wire:click="complete"
                    :disabled="! $this->isBalanced"
                >
                    Complete Reconciliation
                </flux:button>
            @else
                <p class="text-sm text-zinc-500">
                    Completed {{ $reconciliation->completed_at->format('M j, Y \a\t g:i A') }}
                    by {{ $reconciliation->completedBy?->name ?? 'Unknown' }}
                </p>
            @endif
        </div>
    </flux:card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Uncleared lines --}}
        <flux:card>
            <flux:heading size="md" class="mb-4">
                Uncleared Items
                <flux:badge color="zinc" size="sm">{{ $this->unclearedLines->count() }}</flux:badge>
            </flux:heading>

            @if ($this->unclearedLines->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400 py-4 text-center">All items cleared.</p>
            @else
                <div class="space-y-1">
                    @foreach ($this->unclearedLines as $line)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/30 text-sm" wire:key="uncleared-{{ $line->id }}">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate">{{ $line->journalEntry->description }}</p>
                                <p class="text-xs text-zinc-500">{{ $line->journalEntry->date->format('M j, Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3 ml-3">
                                <span class="font-mono text-sm {{ $line->debit > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $line->debit > 0 ? '+' : '-' }}${{ number_format(($line->debit ?: $line->credit) / 100, 2) }}
                                </span>
                                @if ($reconciliation->status !== 'completed')
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="markCleared({{ $line->id }})"
                                    >
                                        Clear
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        {{-- Cleared lines --}}
        <flux:card>
            <flux:heading size="md" class="mb-4">
                Cleared Items
                <flux:badge color="green" size="sm">{{ $this->clearedLines->count() }}</flux:badge>
            </flux:heading>

            @if ($this->clearedLines->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400 py-4 text-center">No items cleared yet.</p>
            @else
                <div class="space-y-1">
                    @foreach ($this->clearedLines as $rl)
                        @php $line = $rl->journalEntryLine; @endphp
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-green-50 dark:bg-green-900/10 text-sm" wire:key="cleared-{{ $rl->id }}">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate">{{ $line->journalEntry->description }}</p>
                                <p class="text-xs text-zinc-500">{{ $line->journalEntry->date->format('M j, Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3 ml-3">
                                <span class="font-mono text-sm {{ $line->debit > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $line->debit > 0 ? '+' : '-' }}${{ number_format(($line->debit ?: $line->credit) / 100, 2) }}
                                </span>
                                @if ($reconciliation->status !== 'completed')
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="unmarkCleared({{ $rl->id }})"
                                    >
                                        Unclear
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
