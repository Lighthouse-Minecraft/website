<?php

use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->authorize('finance-view');
    }

    public function getCashPositionProperty(): array
    {
        $accounts = FinancialAccount::where('is_bank_account', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $items = [];
        $total = 0;

        foreach ($accounts as $account) {
            $balance = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.account_id', $account->id)
                ->where('je.status', 'posted')
                ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as balance')
                ->value('balance');

            $items[] = ['name' => $account->name, 'balance' => $balance];
            $total += $balance;
        }

        return ['accounts' => $items, 'total' => $total];
    }

    public function getCurrentMonthProperty(): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $income = (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where('fa.type', 'revenue')
            ->whereRaw('DATE(je.date) BETWEEN ? AND ?', [$start, $end])
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
            ->value('total');

        $expenses = (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where('fa.type', 'expense')
            ->whereRaw('DATE(je.date) BETWEEN ? AND ?', [$start, $end])
            ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
            ->value('total');

        $period = FinancialPeriod::where('start_date', '<=', today())
            ->where('end_date', '>=', today())
            ->first();

        $incomeBudget = 0;
        $expenseBudget = 0;

        if ($period) {
            $incomeBudget = (int) FinancialBudget::where('period_id', $period->id)
                ->whereHas('account', fn ($q) => $q->where('type', 'revenue'))
                ->sum('amount');

            $expenseBudget = (int) FinancialBudget::where('period_id', $period->id)
                ->whereHas('account', fn ($q) => $q->where('type', 'expense'))
                ->sum('amount');
        }

        return [
            'income' => $income,
            'expenses' => $expenses,
            'income_budget' => $incomeBudget,
            'expense_budget' => $expenseBudget,
            'chart_data' => [
                ['category' => 'Income', 'actual' => round($income / 100, 2), 'budget' => round($incomeBudget / 100, 2)],
                ['category' => 'Expenses', 'actual' => round($expenses / 100, 2), 'budget' => round($expenseBudget / 100, 2)],
            ],
        ];
    }

    public function getSixMonthTrendProperty(): array
    {
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $start = $month->copy()->startOfMonth()->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $income = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
                ->where('je.status', 'posted')
                ->whereNot('je.entry_type', 'closing')
                ->where('fa.type', 'revenue')
                ->whereRaw('DATE(je.date) BETWEEN ? AND ?', [$start, $end])
                ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
                ->value('total');

            $expense = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
                ->where('je.status', 'posted')
                ->whereNot('je.entry_type', 'closing')
                ->where('fa.type', 'expense')
                ->whereRaw('DATE(je.date) BETWEEN ? AND ?', [$start, $end])
                ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
                ->value('total');

            $balance = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
                ->where('je.status', 'posted')
                ->where('fa.is_bank_account', true)
                ->whereRaw('DATE(je.date) <= ?', [$end])
                ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
                ->value('total');

            $data[] = [
                'month' => $start,
                'income' => round($income / 100, 2),
                'expense' => round($expense / 100, 2),
                'balance' => round($balance / 100, 2),
            ];
        }

        return $data;
    }

    public function getYtdSummaryProperty(): array
    {
        $todaysPeriod = FinancialPeriod::where('start_date', '<=', today())
            ->where('end_date', '>=', today())
            ->first();

        if (! $todaysPeriod) {
            return ['income' => 0, 'expenses' => 0, 'income_budget' => 0, 'expense_budget' => 0, 'fiscal_year' => null];
        }

        $fiscalYear = $todaysPeriod->fiscal_year;
        $periodIds = FinancialPeriod::where('fiscal_year', $fiscalYear)->pluck('id');

        $income = (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where('fa.type', 'revenue')
            ->whereIn('je.period_id', $periodIds)
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
            ->value('total');

        $expenses = (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where('fa.type', 'expense')
            ->whereIn('je.period_id', $periodIds)
            ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
            ->value('total');

        $incomeBudget = (int) FinancialBudget::whereIn('period_id', $periodIds)
            ->whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->sum('amount');

        $expenseBudget = (int) FinancialBudget::whereIn('period_id', $periodIds)
            ->whereHas('account', fn ($q) => $q->where('type', 'expense'))
            ->sum('amount');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'income_budget' => $incomeBudget,
            'expense_budget' => $expenseBudget,
            'fiscal_year' => $fiscalYear,
        ];
    }

    public function getNetAssetsProperty(): array
    {
        $totalAssets = (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where('fa.type', 'asset')
            ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
            ->value('total');

        $totalLiabilities = (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where('fa.type', 'liability')
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
            ->value('total');

        $total = $totalAssets - $totalLiabilities;

        $restricted = (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->whereNotNull('je.restricted_fund_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN fa.type = 'revenue' THEN jel.credit - jel.debit ELSE 0 END) - SUM(CASE WHEN fa.type = 'expense' THEN jel.debit - jel.credit ELSE 0 END), 0) as total")
            ->value('total');

        return [
            'total' => $total,
            'restricted' => $restricted,
            'unrestricted' => $total - $restricted,
        ];
    }

    public function getPendingDraftsProperty(): int
    {
        return FinancialJournalEntry::where('status', 'draft')->count();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Finance Dashboard</flux:heading>
            <flux:text variant="subtle">Financial overview — posted entries only.</flux:text>
        </div>
    </div>

    @include('livewire.finance.partials.nav')

    {{-- Cash Position --}}
    <div>
        <flux:heading size="md" class="mb-3">Cash Position</flux:heading>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($this->cashPosition['accounts'] as $account)
                <flux:card class="p-4">
                    <flux:text variant="subtle" class="text-xs">{{ $account['name'] }}</flux:text>
                    <p class="text-2xl font-mono font-semibold mt-1 {{ $account['balance'] >= 0 ? '' : 'text-red-600 dark:text-red-400' }}">
                        {{ $account['balance'] < 0 ? '-' : '' }}${{ number_format(abs($account['balance']) / 100, 2) }}
                    </p>
                </flux:card>
            @endforeach

            <flux:card class="p-4 border-2 border-zinc-300 dark:border-zinc-600">
                <flux:text variant="subtle" class="text-xs font-semibold">Total Cash</flux:text>
                <p class="text-2xl font-mono font-semibold mt-1 {{ $this->cashPosition['total'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $this->cashPosition['total'] < 0 ? '-' : '' }}${{ number_format(abs($this->cashPosition['total']) / 100, 2) }}
                </p>
            </flux:card>
        </div>
    </div>

    {{-- Current Month --}}
    @php $currentMonth = $this->currentMonth; @endphp
    <flux:card>
        <flux:heading size="md" class="mb-4">Current Month — {{ now()->format('F Y') }}</flux:heading>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
            <div>
                <flux:text variant="subtle" class="text-xs">Income</flux:text>
                <p class="text-3xl font-mono font-semibold text-green-600 dark:text-green-400 mt-1">
                    ${{ number_format($currentMonth['income'] / 100, 2) }}
                </p>
                @if ($currentMonth['income_budget'] > 0)
                    <flux:text variant="subtle" class="text-xs mt-1">
                        Budget: ${{ number_format($currentMonth['income_budget'] / 100, 2) }}
                    </flux:text>
                @endif
            </div>
            <div>
                <flux:text variant="subtle" class="text-xs">Expenses</flux:text>
                <p class="text-3xl font-mono font-semibold text-red-600 dark:text-red-400 mt-1">
                    ${{ number_format($currentMonth['expenses'] / 100, 2) }}
                </p>
                @if ($currentMonth['expense_budget'] > 0)
                    <flux:text variant="subtle" class="text-xs mt-1">
                        Budget: ${{ number_format($currentMonth['expense_budget'] / 100, 2) }}
                    </flux:text>
                @endif
            </div>
        </div>

        @if ($currentMonth['income_budget'] > 0 || $currentMonth['expense_budget'] > 0)
            <flux:chart :value="$currentMonth['chart_data']" class="aspect-[4/1]">
                <flux:chart.svg gutter="8 8 28 8">
                    <flux:chart.axis axis="x" field="category">
                        <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                        <flux:chart.axis.line class="text-zinc-600" />
                    </flux:chart.axis>
                    <flux:chart.axis axis="y">
                        <flux:chart.axis.grid class="text-zinc-700" />
                        <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                    </flux:chart.axis>
                    <flux:chart.line field="actual" class="text-blue-500" />
                    <flux:chart.point field="actual" class="text-blue-400" />
                    <flux:chart.line field="budget" class="text-zinc-500" />
                    <flux:chart.point field="budget" class="text-zinc-400" />
                    <flux:chart.cursor />
                </flux:chart.svg>
                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="category" />
                    <flux:chart.tooltip.value field="actual" label="Actual" />
                    <flux:chart.tooltip.value field="budget" label="Budget" />
                </flux:chart.tooltip>
            </flux:chart>
        @endif
    </flux:card>

    {{-- 6-Month Trend --}}
    <flux:card>
        <flux:heading size="md" class="mb-4">6-Month Trend</flux:heading>
        <flux:chart :value="$this->sixMonthTrend" class="aspect-[3/1]">
            <flux:chart.svg gutter="8 8 28 8">
                <flux:chart.axis axis="x" field="month" interval="month" :format="['month' => 'short', 'year' => 'numeric']">
                    <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                    <flux:chart.axis.line class="text-zinc-600" />
                </flux:chart.axis>
                <flux:chart.axis axis="y" tick-start="0">
                    <flux:chart.axis.grid class="text-zinc-700" />
                    <flux:chart.axis.tick class="text-zinc-400 text-xs" />
                </flux:chart.axis>
                <flux:chart.line field="income" class="text-green-500" />
                <flux:chart.point field="income" class="text-green-400" />
                <flux:chart.line field="expense" class="text-red-500" />
                <flux:chart.point field="expense" class="text-red-400" />
                <flux:chart.line field="balance" class="text-blue-500" />
                <flux:chart.point field="balance" class="text-blue-400" />
                <flux:chart.cursor />
            </flux:chart.svg>
            <flux:chart.tooltip>
                <flux:chart.tooltip.heading field="month" :format="['month' => 'short', 'year' => 'numeric']" />
                <flux:chart.tooltip.value field="income" label="Income" />
                <flux:chart.tooltip.value field="expense" label="Expenses" />
                <flux:chart.tooltip.value field="balance" label="Balance" />
            </flux:chart.tooltip>
        </flux:chart>
    </flux:card>

    {{-- YTD Summary + Net Assets side by side --}}
    @php $ytd = $this->ytdSummary; $netAssets = $this->netAssets; @endphp
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- YTD Summary --}}
        <flux:card>
            <flux:heading size="md" class="mb-4">
                YTD Summary
                @if ($ytd['fiscal_year'])
                    <span class="text-sm font-normal text-zinc-400">FY{{ $ytd['fiscal_year'] }}</span>
                @endif
            </flux:heading>
            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between">
                        <flux:text variant="subtle" class="text-sm">Income</flux:text>
                        <span class="font-mono font-semibold text-green-600 dark:text-green-400">
                            ${{ number_format($ytd['income'] / 100, 2) }}
                        </span>
                    </div>
                    @if ($ytd['income_budget'] > 0)
                        <div class="flex items-center justify-between">
                            <flux:text variant="subtle" class="text-xs">Budget</flux:text>
                            <flux:text variant="subtle" class="text-xs font-mono">
                                ${{ number_format($ytd['income_budget'] / 100, 2) }}
                            </flux:text>
                        </div>
                    @endif
                </div>
                <flux:separator variant="subtle" />
                <div>
                    <div class="flex items-center justify-between">
                        <flux:text variant="subtle" class="text-sm">Expenses</flux:text>
                        <span class="font-mono font-semibold text-red-600 dark:text-red-400">
                            ${{ number_format($ytd['expenses'] / 100, 2) }}
                        </span>
                    </div>
                    @if ($ytd['expense_budget'] > 0)
                        <div class="flex items-center justify-between">
                            <flux:text variant="subtle" class="text-xs">Budget</flux:text>
                            <flux:text variant="subtle" class="text-xs font-mono">
                                ${{ number_format($ytd['expense_budget'] / 100, 2) }}
                            </flux:text>
                        </div>
                    @endif
                </div>
                <flux:separator variant="subtle" />
                <div class="flex items-center justify-between">
                    <flux:text class="text-sm font-semibold">Net</flux:text>
                    @php $ytdNet = $ytd['income'] - $ytd['expenses']; @endphp
                    <span class="font-mono font-semibold {{ $ytdNet >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $ytdNet < 0 ? '-' : '' }}${{ number_format(abs($ytdNet) / 100, 2) }}
                    </span>
                </div>
            </div>
        </flux:card>

        {{-- Net Assets --}}
        <flux:card>
            <flux:heading size="md" class="mb-4">Net Assets</flux:heading>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:text variant="subtle" class="text-sm">Total Net Assets</flux:text>
                    <span class="font-mono font-semibold text-xl {{ $netAssets['total'] >= 0 ? '' : 'text-red-600 dark:text-red-400' }}">
                        {{ $netAssets['total'] < 0 ? '-' : '' }}${{ number_format(abs($netAssets['total']) / 100, 2) }}
                    </span>
                </div>
                <flux:separator variant="subtle" />
                <div class="flex items-center justify-between">
                    <flux:text variant="subtle" class="text-sm">Unrestricted</flux:text>
                    <span class="font-mono">
                        {{ $netAssets['unrestricted'] < 0 ? '-' : '' }}${{ number_format(abs($netAssets['unrestricted']) / 100, 2) }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <flux:text variant="subtle" class="text-sm">Restricted</flux:text>
                    <span class="font-mono">
                        ${{ number_format($netAssets['restricted'] / 100, 2) }}
                    </span>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Pending Drafts --}}
    <flux:card>
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="md">Pending Drafts</flux:heading>
                <flux:text variant="subtle" class="text-sm mt-1">Journal entries awaiting review and posting.</flux:text>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-3xl font-bold {{ $this->pendingDrafts > 0 ? 'text-amber-500' : 'text-zinc-400' }}">
                    {{ $this->pendingDrafts }}
                </span>
                @if ($this->pendingDrafts > 0)
                    <flux:button href="{{ route('finance.journal.index') }}" wire:navigate variant="ghost" size="sm" icon="arrow-right">
                        View Journal
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>
</div>
