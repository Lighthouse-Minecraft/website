<?php

use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\FinancialRestrictedFund;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->authorize('finance-community-view');
    }

    public function getClosedPeriodsProperty()
    {
        return FinancialPeriod::where('status', 'closed')
            ->orderByDesc('fiscal_year')
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Get revenue category totals for a closed period.
     * Returns array of ['account_id', 'code', 'name', 'total'] in cents.
     */
    public function getPeriodRevenueSummary(int $periodId): array
    {
        return DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.period_id', $periodId)
            ->where('je.status', 'posted')
            ->whereNot('je.entry_type', 'closing')
            ->where('fa.type', 'revenue')
            ->select('fa.id as account_id', 'fa.code', 'fa.name')
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
            ->groupBy('fa.id', 'fa.code', 'fa.name')
            ->orderBy('fa.code')
            ->get()
            ->map(fn ($row) => [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'total' => (int) $row->total,
            ])
            ->toArray();
    }

    /**
     * Get expense category totals for a closed period.
     * Returns array of ['account_id', 'code', 'name', 'total'] in cents.
     */
    public function getPeriodExpenseSummary(int $periodId): array
    {
        return DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.period_id', $periodId)
            ->where('je.status', 'posted')
            ->whereNot('je.entry_type', 'closing')
            ->where('fa.type', 'expense')
            ->select('fa.id as account_id', 'fa.code', 'fa.name')
            ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
            ->groupBy('fa.id', 'fa.code', 'fa.name')
            ->orderBy('fa.code')
            ->get()
            ->map(fn ($row) => [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'total' => (int) $row->total,
            ])
            ->toArray();
    }

    /**
     * Get restricted fund activity (received/spent/remaining) for a period.
     * Only returns funds with activity in the period.
     */
    public function getPeriodRestrictedFundSummary(int $periodId): array
    {
        // Find journal entries with restricted funds in this period
        $entryIds = FinancialJournalEntry::where('period_id', $periodId)
            ->where('status', 'posted')
            ->whereNot('entry_type', 'closing')
            ->whereNotNull('restricted_fund_id')
            ->pluck('id');

        if ($entryIds->isEmpty()) {
            return [];
        }

        $fundIds = FinancialJournalEntry::whereIn('id', $entryIds)
            ->distinct()
            ->pluck('restricted_fund_id');

        $funds = FinancialRestrictedFund::whereIn('id', $fundIds)->get();
        $result = [];

        foreach ($funds as $fund) {
            $received = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
                ->where('je.period_id', $periodId)
                ->where('je.status', 'posted')
                ->where('je.restricted_fund_id', $fund->id)
                ->where('je.entry_type', 'income')
                ->where('fa.type', 'revenue')
                ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
                ->value('total');

            $spent = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
                ->where('je.period_id', $periodId)
                ->where('je.status', 'posted')
                ->where('je.restricted_fund_id', $fund->id)
                ->where('je.entry_type', 'expense')
                ->where('fa.type', 'expense')
                ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
                ->value('total');

            if ($received !== 0 || $spent !== 0) {
                $result[] = [
                    'fund_id' => $fund->id,
                    'name' => $fund->name,
                    'received' => $received,
                    'spent' => $spent,
                    'remaining' => $received - $spent,
                ];
            }
        }

        return $result;
    }
}; ?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Community Finances</flux:heading>
            <flux:text variant="subtle">Monthly financial summaries for closed fiscal periods. Revenue and expenses shown by category.</flux:text>
        </div>
        @can('finance-view')
            <flux:button variant="ghost" href="{{ route('finance.reports.index') }}" wire:navigate icon="chart-bar">
                Staff Finance Portal
            </flux:button>
        @endcan
    </div>

    @if ($this->closedPeriods->isEmpty())
        <flux:card>
            <p class="text-sm text-zinc-500 py-8 text-center">No closed fiscal periods yet. Check back after the first period is reconciled and closed.</p>
        </flux:card>
    @else
        <div class="space-y-6">
            @foreach ($this->closedPeriods as $period)
                @php
                    $revenue  = $this->getPeriodRevenueSummary($period->id);
                    $expenses = $this->getPeriodExpenseSummary($period->id);
                    $funds    = $this->getPeriodRestrictedFundSummary($period->id);

                    $totalRevenue  = array_sum(array_column($revenue, 'total'));
                    $totalExpenses = array_sum(array_column($expenses, 'total'));
                    $netChange     = $totalRevenue - $totalExpenses;
                @endphp

                <flux:card>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <flux:heading size="md">{{ $period->name }}</flux:heading>
                            <flux:text variant="subtle" class="text-xs">
                                {{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }}
                                @if ($period->closed_at)
                                    · Closed {{ $period->closed_at->format('M j, Y') }}
                                @endif
                            </flux:text>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-zinc-500">Net</p>
                            <p class="text-lg font-mono font-semibold {{ $netChange >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $netChange >= 0 ? '' : '-' }}${{ number_format(abs($netChange) / 100, 2) }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Revenue --}}
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Revenue</h3>
                            @if (empty($revenue))
                                <p class="text-xs text-zinc-400 italic">No revenue this period.</p>
                            @else
                                <div class="space-y-1">
                                    @foreach ($revenue as $row)
                                        <div class="flex justify-between text-sm py-0.5">
                                            <span class="text-zinc-600 dark:text-zinc-400">{{ $row['name'] }}</span>
                                            <span class="font-mono">${{ number_format($row['total'] / 100, 2) }}</span>
                                        </div>
                                    @endforeach
                                    <div class="flex justify-between text-sm font-semibold border-t border-zinc-200 dark:border-zinc-700 pt-1 mt-1">
                                        <span>Total Revenue</span>
                                        <span class="font-mono">${{ number_format($totalRevenue / 100, 2) }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Expenses --}}
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Expenses</h3>
                            @if (empty($expenses))
                                <p class="text-xs text-zinc-400 italic">No expenses this period.</p>
                            @else
                                <div class="space-y-1">
                                    @foreach ($expenses as $row)
                                        <div class="flex justify-between text-sm py-0.5">
                                            <span class="text-zinc-600 dark:text-zinc-400">{{ $row['name'] }}</span>
                                            <span class="font-mono">${{ number_format($row['total'] / 100, 2) }}</span>
                                        </div>
                                    @endforeach
                                    <div class="flex justify-between text-sm font-semibold border-t border-zinc-200 dark:border-zinc-700 pt-1 mt-1">
                                        <span>Total Expenses</span>
                                        <span class="font-mono">${{ number_format($totalExpenses / 100, 2) }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Restricted Fund Summaries --}}
                    @if (! empty($funds))
                        <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Restricted Funds</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($funds as $fund)
                                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-3">
                                        <p class="text-sm font-medium mb-1">{{ $fund['name'] }}</p>
                                        <div class="space-y-0.5 text-xs">
                                            <div class="flex justify-between">
                                                <span class="text-zinc-500">Received</span>
                                                <span class="font-mono text-green-600 dark:text-green-400">${{ number_format($fund['received'] / 100, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-zinc-500">Spent</span>
                                                <span class="font-mono text-red-600 dark:text-red-400">${{ number_format($fund['spent'] / 100, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between font-semibold border-t border-zinc-200 dark:border-zinc-700 pt-0.5 mt-0.5">
                                                <span>Remaining</span>
                                                <span class="font-mono {{ $fund['remaining'] >= 0 ? '' : 'text-red-600 dark:text-red-400' }}">
                                                    ${{ number_format($fund['remaining'] / 100, 2) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
