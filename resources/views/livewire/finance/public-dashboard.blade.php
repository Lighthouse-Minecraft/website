<?php

use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialPeriod;
use App\Models\SiteConfig;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Get the last 3 closed fiscal periods (most recent first).
     */
    public function getClosedPeriodsProperty()
    {
        return FinancialPeriod::where('status', 'closed')
            ->orderByDesc('fiscal_year')
            ->orderByDesc('start_date')
            ->limit(3)
            ->get();
    }

    /**
     * Get total posted income for a period (cents).
     */
    public function getPeriodIncome(int $periodId): int
    {
        return (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.period_id', $periodId)
            ->where('je.status', 'posted')
            ->whereNot('je.entry_type', 'closing')
            ->where('fa.type', 'revenue')
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
            ->value('total');
    }

    /**
     * Get total posted expenses for a period (cents).
     */
    public function getPeriodExpenses(int $periodId): int
    {
        return (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('je.period_id', $periodId)
            ->where('je.status', 'posted')
            ->whereNot('je.entry_type', 'closing')
            ->where('fa.type', 'expense')
            ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
            ->value('total');
    }

    /**
     * Get current open period (for donation goal progress).
     */
    public function getCurrentPeriodProperty(): ?FinancialPeriod
    {
        return FinancialPeriod::where('status', '!=', 'closed')
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->first();
    }

    /**
     * Donation goal for the current period = sum of budgeted amounts
     * for accounts with 'donations' subtype (revenue accounts).
     * Falls back to the legacy SiteConfig value if no budget is set.
     */
    public function getDonationGoalCentsProperty(): int
    {
        if (! $this->currentPeriod) {
            return 0;
        }

        $donationAccounts = FinancialAccount::where('type', 'revenue')
            ->where('subtype', 'donations')
            ->where('is_active', true)
            ->pluck('id');

        if ($donationAccounts->isEmpty()) {
            return 0;
        }

        $budgeted = (int) FinancialBudget::where('period_id', $this->currentPeriod->id)
            ->whereIn('account_id', $donationAccounts)
            ->sum('amount');

        // Fall back to legacy SiteConfig donation_goal if no budget set
        if ($budgeted === 0) {
            $legacyGoal = (int) SiteConfig::getValue('donation_goal', '0');

            return $legacyGoal * 100; // Legacy value is in dollars
        }

        return $budgeted;
    }

    /**
     * Actual donated amount for the current period (posted income entries
     * against donation revenue accounts).
     */
    public function getDonationProgressCentsProperty(): int
    {
        if (! $this->currentPeriod) {
            return 0;
        }

        $donationAccounts = FinancialAccount::where('type', 'revenue')
            ->where('subtype', 'donations')
            ->where('is_active', true)
            ->pluck('id');

        if ($donationAccounts->isEmpty()) {
            return 0;
        }

        return (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.period_id', $this->currentPeriod->id)
            ->where('je.status', 'posted')
            ->whereIn('jel.account_id', $donationAccounts)
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
            ->value('total');
    }

    public function getDonationGoalPercentProperty(): int
    {
        if ($this->donationGoalCents === 0) {
            return 0;
        }

        return (int) min(100, round(($this->donationProgressCents / $this->donationGoalCents) * 100));
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Community Finances</flux:heading>
        <flux:text variant="subtle">A high-level look at recent monthly finances. Individual transaction details are not shown here.</flux:text>
    </div>

    {{-- Donation Goal Progress (current month) --}}
    @if ($this->currentPeriod && $this->donationGoalCents > 0)
        <flux:card>
            <flux:heading size="md" class="mb-1">{{ $this->currentPeriod->name }} Donation Goal</flux:heading>
            <flux:text variant="subtle" class="text-xs mb-3">Progress toward this month's donation goal.</flux:text>

            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span>${{ number_format($this->donationProgressCents / 100, 0) }} raised</span>
                    <span class="text-zinc-500">Goal: ${{ number_format($this->donationGoalCents / 100, 0) }}</span>
                </div>
                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3 overflow-hidden">
                    <div
                        class="h-3 rounded-full transition-all duration-500 {{ $this->donationGoalPercent >= 100 ? 'bg-green-500' : 'bg-blue-500' }}"
                        style="width: {{ $this->donationGoalPercent }}%"
                    ></div>
                </div>
                <p class="text-xs text-zinc-500">
                    {{ $this->donationGoalPercent }}% of goal
                    @if ($this->donationGoalPercent >= 100)
                        — Goal reached! 🎉
                    @endif
                </p>
            </div>
        </flux:card>
    @endif

    {{-- Last 3 Closed Periods --}}
    @if ($this->closedPeriods->isEmpty())
        <flux:card>
            <p class="text-sm text-zinc-500 py-8 text-center">No financial data available yet.</p>
        </flux:card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($this->closedPeriods as $period)
                @php
                    $income   = $this->getPeriodIncome($period->id);
                    $expenses = $this->getPeriodExpenses($period->id);
                    $net      = $income - $expenses;
                @endphp
                <flux:card>
                    <flux:heading size="sm" class="mb-3">{{ $period->name }}</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">Income</span>
                            <span class="font-mono text-green-600 dark:text-green-400">${{ number_format($income / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500">Expenses</span>
                            <span class="font-mono text-red-600 dark:text-red-400">${{ number_format($expenses / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold border-t border-zinc-200 dark:border-zinc-700 pt-2 mt-2">
                            <span>Net</span>
                            <span class="font-mono {{ $net >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $net >= 0 ? '' : '-' }}${{ number_format(abs($net) / 100, 2) }}
                            </span>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <flux:text variant="subtle" class="text-xs">
            Figures reflect posted entries for closed fiscal months only. Data is updated when periods are closed by finance staff.
        </flux:text>
    @endif
</div>
