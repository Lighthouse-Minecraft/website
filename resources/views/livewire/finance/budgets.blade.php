<?php

use App\Actions\CopyPriorYearBudgets;
use App\Actions\GenerateFinancialPeriods;
use App\Actions\ParseDollarAmount;
use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialPeriod;
use App\Models\SiteConfig;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public int $selectedYear;

    public string $activeTab = 'entry';

    public function mount(): void
    {
        $this->authorize('finance-view');

        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        $now = now();
        $this->selectedYear = ($now->month >= $startMonth) ? $now->year + 1 : $now->year;

        $this->ensurePeriodsExist();
    }

    public function updatedSelectedYear(): void
    {
        $this->ensurePeriodsExist();
    }

    public function getAvailableYearsProperty(): array
    {
        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        $now = now();
        $currentFy = ($now->month >= $startMonth) ? $now->year + 1 : $now->year;

        return range($currentFy - 3, $currentFy + 1);
    }

    public function getPeriodsProperty()
    {
        return FinancialPeriod::where('fiscal_year', $this->selectedYear)
            ->orderBy('start_date')
            ->get();
    }

    public function getAccountsProperty()
    {
        return FinancialAccount::whereIn('type', ['revenue', 'expense'])
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('code')
            ->get();
    }

    public function getBudgetDataProperty(): array
    {
        $budgets = FinancialBudget::whereIn('period_id', $this->periods->pluck('id'))->get();

        $data = [];
        foreach ($budgets as $budget) {
            $data[$budget->account_id][$budget->period_id] = $budget->amount;
        }

        return $data;
    }

    public function getActualDataProperty(): array
    {
        $periodIds = $this->periods->pluck('id');
        $accountIds = $this->accounts->pluck('id');

        $rows = DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.status', 'posted')
            ->whereIn('je.period_id', $periodIds)
            ->whereIn('jel.account_id', $accountIds)
            ->groupBy('jel.account_id', 'je.period_id')
            ->select(
                'jel.account_id',
                'je.period_id',
                DB::raw('SUM(jel.debit) as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit')
            )
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $data[$row->account_id][$row->period_id] = [
                'debit' => (int) $row->total_debit,
                'credit' => (int) $row->total_credit,
            ];
        }

        return $data;
    }

    public function updateBudget(int $accountId, int $periodId, string $amount): void
    {
        $this->authorize('finance-manage');

        // Validate that the account is an active revenue/expense account
        $account = FinancialAccount::where('id', $accountId)
            ->whereIn('type', ['revenue', 'expense'])
            ->where('is_active', true)
            ->first();

        if (! $account) {
            Flux::toast('Invalid account.', 'Error', variant: 'danger');

            return;
        }

        // Validate that the period belongs to the selected fiscal year
        $period = FinancialPeriod::where('id', $periodId)
            ->where('fiscal_year', $this->selectedYear)
            ->first();

        if (! $period) {
            Flux::toast('Invalid period.', 'Error', variant: 'danger');

            return;
        }

        try {
            $cents = ParseDollarAmount::run($amount ?: '0');
        } catch (\InvalidArgumentException) {
            Flux::toast('Invalid amount entered.', 'Error', variant: 'danger');

            return;
        }

        FinancialBudget::updateOrCreate(
            ['account_id' => $accountId, 'period_id' => $periodId],
            ['amount' => $cents]
        );
    }

    public function copyPriorYear(): void
    {
        $this->authorize('finance-manage');

        $copied = CopyPriorYearBudgets::run($this->selectedYear - 1, $this->selectedYear);

        if ($copied === 0) {
            Flux::toast('No budgets found in prior year to copy.', 'Nothing copied', variant: 'danger');
        } else {
            Flux::toast("{$copied} budget entries copied from FY ".($this->selectedYear - 1).'.', 'Done', variant: 'success');
        }
    }

    private function ensurePeriodsExist(): void
    {
        if (! auth()->user()?->can('finance-manage')) {
            return;
        }

        // Reject years outside a safe range to prevent generation of arbitrary periods
        $now = now();
        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        $currentFy = ($now->month >= $startMonth) ? $now->year + 1 : $now->year;

        if ($this->selectedYear < $currentFy - 5 || $this->selectedYear > $currentFy + 1) {
            return;
        }

        GenerateFinancialPeriods::run($this->selectedYear, $startMonth);
    }

    private function actualForAccount(FinancialAccount $account, int $periodId, array $actualData): int
    {
        $row = $actualData[$account->id][$periodId] ?? null;

        if (! $row) {
            return 0;
        }

        // Revenue accounts: actual = credits - debits
        // Expense accounts: actual = debits - credits
        return $account->type === 'revenue'
            ? ($row['credit'] - $row['debit'])
            : ($row['debit'] - $row['credit']);
    }
}; ?>

<div class="space-y-6">
    @include('livewire.finance.partials.nav')

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Budget Management</flux:heading>
            <flux:text variant="subtle">Enter monthly budget amounts by fiscal year.</flux:text>
        </div>

        <div class="flex items-center gap-3">
            <flux:field class="mb-0">
                <flux:select wire:model.live="selectedYear" class="w-36">
                    @foreach ($this->availableYears as $year)
                        <flux:select.option value="{{ $year }}">FY {{ $year }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            @can('finance-manage')
                <flux:button variant="outline" wire:click="copyPriorYear" size="sm">
                    Copy from FY {{ $selectedYear - 1 }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('activeTab', 'entry')"
            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                {{ $activeTab === 'entry'
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            Budget Entry
        </button>
        <button
            wire:click="$set('activeTab', 'variance')"
            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                {{ $activeTab === 'variance'
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            Budget vs. Actual
        </button>
    </div>

    @if ($activeTab === 'entry')
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="text-left py-2 px-3 font-medium text-zinc-600 dark:text-zinc-400 min-w-48">Account</th>
                        @foreach ($this->periods as $period)
                            <th class="text-right py-2 px-2 font-medium text-zinc-600 dark:text-zinc-400 min-w-20">
                                {{ $period->start_date->format('M') }}
                            </th>
                        @endforeach
                        <th class="text-right py-2 px-3 font-medium text-zinc-600 dark:text-zinc-400 min-w-24">FY Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $budgetData = $this->budgetData;
                        $prevType = null;
                    @endphp

                    @foreach ($this->accounts as $account)
                        @if ($prevType !== $account->type)
                            <tr wire:key="type-header-{{ $account->type }}">
                                <td colspan="{{ count($this->periods) + 2 }}" class="py-1 px-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50">
                                    {{ ucfirst($account->type) }}
                                </td>
                            </tr>
                            @php $prevType = $account->type; @endphp
                        @endif

                        @php
                            $rowTotal = 0;
                            foreach ($this->periods as $period) {
                                $rowTotal += $budgetData[$account->id][$period->id] ?? 0;
                            }
                        @endphp

                        <tr wire:key="account-{{ $account->id }}" class="border-b border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                            <td class="py-1.5 px-3 text-zinc-700 dark:text-zinc-300">
                                <span class="text-xs text-zinc-400 mr-1">{{ $account->code }}</span>
                                {{ $account->name }}
                            </td>

                            @foreach ($this->periods as $period)
                                <td wire:key="budget-{{ $account->id }}-{{ $period->id }}" class="py-1 px-1">
                                    @can('finance-manage')
                                        <input
                                            type="text"
                                            value="{{ isset($budgetData[$account->id][$period->id]) && $budgetData[$account->id][$period->id] > 0 ? number_format($budgetData[$account->id][$period->id] / 100, 2) : '' }}"
                                            wire:change="updateBudget({{ $account->id }}, {{ $period->id }}, $event.target.value)"
                                            placeholder="0.00"
                                            class="w-full text-right text-sm bg-transparent border border-transparent hover:border-zinc-300 dark:hover:border-zinc-600 focus:border-blue-400 dark:focus:border-blue-500 rounded px-1 py-0.5 outline-none"
                                        />
                                    @else
                                        <span class="block text-right px-1">
                                            @if (isset($budgetData[$account->id][$period->id]) && $budgetData[$account->id][$period->id] > 0)
                                                ${{ number_format($budgetData[$account->id][$period->id] / 100, 2) }}
                                            @else
                                                <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                            @endif
                                        </span>
                                    @endcan
                                </td>
                            @endforeach

                            <td class="py-1.5 px-3 text-right font-medium {{ $rowTotal > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-400' }}">
                                @if ($rowTotal > 0)
                                    ${{ number_format($rowTotal / 100, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($this->accounts->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400 py-8 text-center">No active revenue or expense accounts found.</p>
            @endif
        </div>
    @endif

    @if ($activeTab === 'variance')
        @php
            $budgetData = $this->budgetData;
            $actualData = $this->actualData;
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="text-left py-2 px-3 font-medium text-zinc-600 dark:text-zinc-400 min-w-48">Account</th>
                        @foreach ($this->periods as $period)
                            <th class="text-right py-2 px-2 font-medium text-zinc-600 dark:text-zinc-400 min-w-24" colspan="2">
                                {{ $period->start_date->format('M') }}
                            </th>
                        @endforeach
                    </tr>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="py-1 px-3 text-xs text-zinc-500"></th>
                        @foreach ($this->periods as $period)
                            <th class="py-1 px-1 text-xs text-right text-zinc-500 min-w-20">Budget</th>
                            <th class="py-1 px-1 text-xs text-right text-zinc-500 min-w-20">Actual</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php $prevType = null; @endphp

                    @foreach ($this->accounts as $account)
                        @if ($prevType !== $account->type)
                            <tr wire:key="var-type-header-{{ $account->type }}">
                                <td colspan="{{ count($this->periods) * 2 + 1 }}" class="py-1 px-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50">
                                    {{ ucfirst($account->type) }}
                                </td>
                            </tr>
                            @php $prevType = $account->type; @endphp
                        @endif

                        <tr wire:key="var-account-{{ $account->id }}" class="border-b border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                            <td class="py-1.5 px-3 text-zinc-700 dark:text-zinc-300">
                                <span class="text-xs text-zinc-400 mr-1">{{ $account->code }}</span>
                                {{ $account->name }}
                            </td>

                            @foreach ($this->periods as $period)
                                @php
                                    $budget  = $budgetData[$account->id][$period->id] ?? 0;
                                    $actual  = $this->actualForAccount($account, $period->id, $actualData);
                                    $variance = $actual - $budget;
                                    // Revenue: positive variance (actual > budget) is good (green)
                                    // Expense: negative variance (actual < budget) is good (green)
                                    $isGood = $account->type === 'revenue' ? $variance >= 0 : $variance <= 0;
                                @endphp

                                <td wire:key="var-budget-{{ $account->id }}-{{ $period->id }}" class="py-1.5 px-1 text-right">
                                    @if ($budget > 0)
                                        ${{ number_format($budget / 100, 2) }}
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>

                                <td wire:key="var-actual-{{ $account->id }}-{{ $period->id }}" class="py-1.5 px-1 text-right">
                                    @if ($actual !== 0)
                                        <span class="{{ $isGood ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            ${{ number_format($actual / 100, 2) }}
                                        </span>
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($this->accounts->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400 py-8 text-center">No active revenue or expense accounts found.</p>
            @endif
        </div>

        <flux:text variant="subtle" class="text-xs">
            Actual amounts reflect posted journal entries only. Drafts are excluded.
            Green = at or under budget; Red = over budget (for expenses) / under target (for revenue).
        </flux:text>
    @endif
</div>
