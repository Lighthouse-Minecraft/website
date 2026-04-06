<?php

use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use App\Models\SiteConfig;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    public string $activeTab = 'activities';

    // Shared filters
    public string $filterFyYear = '';

    public string $filterPeriodId = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    // General Ledger filters
    public string $glAccountId = '';

    public string $glDateFrom = '';

    public string $glDateTo = '';

    public string $glEntryType = '';

    public function mount(): void
    {
        $this->authorize('finance-view');
        $this->filterFyYear = (string) $this->currentFyYear;
    }

    public function getCurrentFyYearProperty(): int
    {
        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        $now = now();

        return ($now->month >= $startMonth) ? $now->year + 1 : $now->year;
    }

    public function getFyYearsProperty(): array
    {
        $years = FinancialPeriod::select('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->toArray();

        if (empty($years)) {
            $years = [$this->currentFyYear];
        }

        return $years;
    }

    public function getFilteredPeriodsProperty()
    {
        return FinancialPeriod::when($this->filterFyYear, fn ($q) => $q->where('fiscal_year', $this->filterFyYear))
            ->orderBy('start_date')
            ->get();
    }

    public function getAllAccountsProperty()
    {
        return FinancialAccount::where('is_active', true)->orderBy('code')->get();
    }

    // ─── Statement of Activities ───────────────────────────────────────────

    public function getActivitiesQueryConstraintsProperty(): array
    {
        $constraints = ['status' => 'posted'];

        if ($this->filterPeriodId) {
            $constraints['period_id'] = (int) $this->filterPeriodId;
        }

        return $constraints;
    }

    /**
     * Get posted-entry net balance for each account of a given type,
     * filtered by the current FY/period/date settings.
     * Returns collection of ['account' => FinancialAccount, 'net' => int (cents)]
     */
    private function getActivitiesAccountTotals(string $type)
    {
        $query = DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->join('financial_periods as fp', 'fp.id', '=', 'je.period_id')
            ->where('fa.type', $type)
            ->where('je.status', 'posted')
            ->whereNot('je.entry_type', 'closing')
            ->select('fa.id as account_id', 'fa.code', 'fa.name')
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as net_credit')
            ->groupBy('fa.id', 'fa.code', 'fa.name')
            ->orderBy('fa.code');

        if ($this->filterPeriodId) {
            $query->where('je.period_id', $this->filterPeriodId);
        } elseif ($this->filterFyYear) {
            $query->where('fp.fiscal_year', $this->filterFyYear);
        }

        if ($this->filterDateFrom) {
            $query->where('je.date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->where('je.date', '<=', $this->filterDateTo);
        }

        return $query->get()->map(fn ($row) => [
            'account_id' => $row->account_id,
            'code' => $row->code,
            'name' => $row->name,
            'net' => (int) $row->net_credit,
        ]);
    }

    public function getRevenueRowsProperty()
    {
        return $this->getActivitiesAccountTotals('revenue');
    }

    public function getExpenseRowsProperty()
    {
        return $this->getActivitiesAccountTotals('expense');
    }

    public function getTotalRevenueProperty(): int
    {
        return (int) $this->revenueRows->sum('net');
    }

    public function getTotalExpensesProperty(): int
    {
        // Expenses have a debit normal balance, so net_credit is negative
        return (int) abs($this->expenseRows->sum('net'));
    }

    public function getNetChangeProperty(): int
    {
        return $this->totalRevenue - $this->totalExpenses;
    }

    // ─── General Ledger ────────────────────────────────────────────────────

    public function getGlLinesProperty()
    {
        if (! $this->glAccountId) {
            return collect();
        }

        $query = DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->leftJoin('financial_vendors as fv', 'fv.id', '=', 'je.vendor_id')
            ->where('jel.account_id', $this->glAccountId)
            ->where('je.status', 'posted')
            ->select(
                'jel.id',
                'je.date',
                'je.description',
                'je.entry_type',
                'jel.debit',
                'jel.credit',
                'jel.memo',
                'fv.name as vendor_name',
            )
            ->orderBy('je.date')
            ->orderBy('je.id');

        if ($this->glDateFrom) {
            $query->where('je.date', '>=', $this->glDateFrom);
        }

        if ($this->glDateTo) {
            $query->where('je.date', '<=', $this->glDateTo);
        }

        if ($this->glEntryType) {
            $query->where('je.entry_type', $this->glEntryType);
        }

        $lines = $query->get();

        // Compute running balance (debit increases balance, credit decreases)
        $runningBalance = 0;

        return $lines->map(function ($line) use (&$runningBalance) {
            $runningBalance += $line->debit - $line->credit;

            return (object) [
                'id' => $line->id,
                'date' => $line->date,
                'description' => $line->description,
                'entry_type' => $line->entry_type,
                'vendor_name' => $line->vendor_name,
                'debit' => (int) $line->debit,
                'credit' => (int) $line->credit,
                'memo' => $line->memo,
                'running_balance' => $runningBalance,
            ];
        });
    }

    public function exportGlCsv(): StreamedResponse
    {
        $this->authorize('finance-view');

        $lines = $this->glLines;
        $account = FinancialAccount::find($this->glAccountId);
        $filename = 'general-ledger-'.($account?->code ?? 'all').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($lines) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Description', 'Vendor', 'Type', 'Debit', 'Credit', 'Running Balance']);

            foreach ($lines as $line) {
                fputcsv($handle, [
                    $line->date,
                    $line->description,
                    $line->vendor_name ?? '',
                    $line->entry_type,
                    $line->debit > 0 ? number_format($line->debit / 100, 2) : '',
                    $line->credit > 0 ? number_format($line->credit / 100, 2) : '',
                    number_format($line->running_balance / 100, 2),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ─── Trial Balance ──────────────────────────────────────────────────────

    public function getTrialBalanceRowsProperty()
    {
        $query = DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->join('financial_periods as fp', 'fp.id', '=', 'je.period_id')
            ->where('je.status', 'posted')
            ->select('fa.id as account_id', 'fa.code', 'fa.name', 'fa.type', 'fa.normal_balance')
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(jel.credit), 0) as total_credit')
            ->groupBy('fa.id', 'fa.code', 'fa.name', 'fa.type', 'fa.normal_balance')
            ->orderBy('fa.code');

        if ($this->filterPeriodId) {
            $query->where('je.period_id', $this->filterPeriodId);
        } elseif ($this->filterFyYear) {
            $query->where('fp.fiscal_year', $this->filterFyYear);
        }

        if ($this->filterDateFrom) {
            $query->where('je.date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->where('je.date', '<=', $this->filterDateTo);
        }

        return $query->get()->map(fn ($row) => [
            'account_id' => $row->account_id,
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'normal_balance' => $row->normal_balance,
            'total_debit' => (int) $row->total_debit,
            'total_credit' => (int) $row->total_credit,
        ]);
    }

    public function getTrialBalanceTotalDebitProperty(): int
    {
        return (int) $this->trialBalanceRows->sum('total_debit');
    }

    public function getTrialBalanceTotalCreditProperty(): int
    {
        return (int) $this->trialBalanceRows->sum('total_credit');
    }

    public function getTrialBalanceIsBalancedProperty(): bool
    {
        return $this->trialBalanceTotalDebit === $this->trialBalanceTotalCredit;
    }

    // ─── Statement of Financial Position (Balance Sheet) ──────────────────

    // Cumulative "as of date" — default to today
    public string $bsAsOfDate = '';

    public function getBsAsOfDateValueProperty(): string
    {
        return $this->bsAsOfDate ?: now()->toDateString();
    }

    /**
     * Cumulative balance for an account from all posted entries up to (and including) asOfDate.
     * debit − credit for each account.
     */
    private function getCumulativeBalance(string $type): object
    {
        return DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('fa.type', $type)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $this->bsAsOfDateValue)
            ->select('fa.id as account_id', 'fa.code', 'fa.name')
            ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as balance')
            ->groupBy('fa.id', 'fa.code', 'fa.name')
            ->orderBy('fa.code')
            ->get()
            ->map(fn ($row) => [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'balance' => (int) $row->balance,
            ]);
    }

    public function getBsAssetRowsProperty()
    {
        return $this->getCumulativeBalance('asset');
    }

    public function getBsNetAssetsUnrestrictedProperty(): int
    {
        // Net Assets — Unrestricted: cumulative credit − debit (credit normal balance)
        return (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('fa.type', 'net_assets')
            ->where('fa.subtype', 'unrestricted')
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $this->bsAsOfDateValue)
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as balance')
            ->value('balance');
    }

    public function getBsNetAssetsRestrictedProperty(): int
    {
        return (int) DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->where('fa.type', 'net_assets')
            ->where('fa.subtype', 'restricted')
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $this->bsAsOfDateValue)
            ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as balance')
            ->value('balance');
    }

    public function getBsTotalAssetsProperty(): int
    {
        return (int) $this->bsAssetRows->sum('balance');
    }

    public function getBsTotalNetAssetsProperty(): int
    {
        return $this->bsNetAssetsUnrestricted + $this->bsNetAssetsRestricted;
    }

    public function getBsIsBalancedProperty(): bool
    {
        return $this->bsTotalAssets === $this->bsTotalNetAssets;
    }

    // ─── Statement of Cash Flows ──────────────────────────────────────────

    public function getCashInflowsProperty(): int
    {
        // Cash received = total revenue posted in the filtered period/date range
        return $this->totalRevenue;
    }

    public function getCashOutflowsProperty(): int
    {
        // Cash paid = total expenses posted in the filtered period/date range
        return $this->totalExpenses;
    }

    public function getNetCashChangeProperty(): int
    {
        return $this->cashInflows - $this->cashOutflows;
    }

    // ─── Budget vs. Actual Variance ────────────────────────────────────────

    public function getVariancePeriodsProperty()
    {
        if (! $this->filterFyYear) {
            return collect();
        }

        return FinancialPeriod::where('fiscal_year', $this->filterFyYear)
            ->orderBy('start_date')
            ->get();
    }

    public function getVarianceAccountsProperty()
    {
        return FinancialAccount::whereIn('type', ['revenue', 'expense'])
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('code')
            ->get();
    }

    public function getVarianceBudgetDataProperty(): array
    {
        $periodIds = $this->variancePeriods->pluck('id');

        if ($periodIds->isEmpty()) {
            return [];
        }

        $budgets = \App\Models\FinancialBudget::whereIn('period_id', $periodIds)->get();
        $data = [];

        foreach ($budgets as $budget) {
            $data[$budget->account_id][$budget->period_id] = $budget->amount;
        }

        return $data;
    }

    public function getVarianceActualDataProperty(): array
    {
        $periodIds = $this->variancePeriods->pluck('id');
        $accountIds = $this->varianceAccounts->pluck('id');

        if ($periodIds->isEmpty() || $accountIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.status', 'posted')
            ->whereNot('je.entry_type', 'closing')
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

    private function varianceActualForAccount(FinancialAccount $account, int $periodId): int
    {
        $row = $this->varianceActualData[$account->id][$periodId] ?? null;

        if (! $row) {
            return 0;
        }

        // Revenue: actual = credits - debits; Expense: actual = debits - credits
        return $account->type === 'revenue'
            ? ($row['credit'] - $row['debit'])
            : ($row['debit'] - $row['credit']);
    }
}; ?>

<div class="space-y-6 print:space-y-4">
    <div class="flex items-center justify-between print:hidden">
        <div>
            <flux:heading size="xl">Financial Reports</flux:heading>
            <flux:text variant="subtle">Statement of Activities, General Ledger, Trial Balance, and more. All figures are based on posted entries only.</flux:text>
        </div>
        <flux:button variant="ghost" size="sm" icon="printer" onclick="window.print()">
            Print / PDF
        </flux:button>
    </div>

    {{-- Print header (only visible when printing) --}}
    <div class="hidden print:block mb-4">
        <h1 class="text-xl font-bold">Financial Report</h1>
        <p class="text-sm text-zinc-500">Generated {{ now()->format('M j, Y') }}</p>
    </div>

    {{-- Tabs --}}
    <flux:tabs wire:model="activeTab" class="print:hidden">
        <flux:tab name="activities">Statement of Activities</flux:tab>
        <flux:tab name="ledger">General Ledger</flux:tab>
        <flux:tab name="trial">Trial Balance</flux:tab>
        <flux:tab name="balance-sheet">Balance Sheet</flux:tab>
        <flux:tab name="cash-flow">Cash Flow</flux:tab>
        <flux:tab name="variance">Budget vs. Actual</flux:tab>
    </flux:tabs>

    {{-- Shared filters (FY/period/date) for Activities, Trial Balance, Cash Flow, and Variance --}}
    @if (in_array($activeTab, ['activities', 'trial', 'cash-flow', 'variance']))
        <flux:card>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <flux:field>
                    <flux:label>Fiscal Year</flux:label>
                    <flux:select wire:model.live="filterFyYear">
                        <flux:select.option value="">All Years</flux:select.option>
                        @foreach ($this->fyYears as $year)
                            <flux:select.option value="{{ $year }}">FY {{ $year }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Period</flux:label>
                    <flux:select wire:model.live="filterPeriodId">
                        <flux:select.option value="">All Periods</flux:select.option>
                        @foreach ($this->filteredPeriods as $period)
                            <flux:select.option value="{{ $period->id }}">{{ $period->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Date From</flux:label>
                    <flux:input type="date" wire:model.live="filterDateFrom" />
                </flux:field>

                <flux:field>
                    <flux:label>Date To</flux:label>
                    <flux:input type="date" wire:model.live="filterDateTo" />
                </flux:field>
            </div>
        </flux:card>
    @endif

    {{-- Statement of Activities --}}
    @if ($activeTab === 'activities')
        <div class="space-y-6">
            {{-- Revenue --}}
            <flux:card>
                <flux:heading size="md" class="mb-4">Revenue</flux:heading>
                @if ($this->revenueRows->isEmpty())
                    <p class="text-sm text-zinc-500 py-4 text-center">No posted revenue entries for this period.</p>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Account</flux:table.column>
                            <flux:table.column>Amount</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->revenueRows as $row)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <span class="text-zinc-500 text-xs mr-2">{{ $row['code'] }}</span>
                                        {{ $row['name'] }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="font-mono">${{ number_format($row['net'] / 100, 2) }}</span>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                            <flux:table.row class="font-semibold border-t border-zinc-200 dark:border-zinc-700">
                                <flux:table.cell>Total Revenue</flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-mono">${{ number_format($this->totalRevenue / 100, 2) }}</span>
                                </flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            {{-- Expenses --}}
            <flux:card>
                <flux:heading size="md" class="mb-4">Expenses</flux:heading>
                @if ($this->expenseRows->isEmpty())
                    <p class="text-sm text-zinc-500 py-4 text-center">No posted expense entries for this period.</p>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Account</flux:table.column>
                            <flux:table.column>Amount</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->expenseRows as $row)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <span class="text-zinc-500 text-xs mr-2">{{ $row['code'] }}</span>
                                        {{ $row['name'] }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="font-mono">${{ number_format(abs($row['net']) / 100, 2) }}</span>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                            <flux:table.row class="font-semibold border-t border-zinc-200 dark:border-zinc-700">
                                <flux:table.cell>Total Expenses</flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-mono">${{ number_format($this->totalExpenses / 100, 2) }}</span>
                                </flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            {{-- Net Change --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <flux:heading size="md">Net Change</flux:heading>
                    <span class="text-2xl font-mono font-bold {{ $this->netChange >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $this->netChange >= 0 ? '' : '-' }}${{ number_format(abs($this->netChange) / 100, 2) }}
                    </span>
                </div>
            </flux:card>
        </div>
    @endif

    {{-- General Ledger --}}
    @if ($activeTab === 'ledger')
        <flux:card>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <flux:field>
                    <flux:label>Account</flux:label>
                    <flux:select wire:model.live="glAccountId">
                        <flux:select.option value="">Select account…</flux:select.option>
                        @foreach ($this->allAccounts as $account)
                            <flux:select.option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Entry Type</flux:label>
                    <flux:select wire:model.live="glEntryType">
                        <flux:select.option value="">All Types</flux:select.option>
                        <flux:select.option value="income">Income</flux:select.option>
                        <flux:select.option value="expense">Expense</flux:select.option>
                        <flux:select.option value="transfer">Transfer</flux:select.option>
                        <flux:select.option value="journal">Journal</flux:select.option>
                        <flux:select.option value="closing">Closing</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Date From</flux:label>
                    <flux:input type="date" wire:model.live="glDateFrom" />
                </flux:field>

                <flux:field>
                    <flux:label>Date To</flux:label>
                    <flux:input type="date" wire:model.live="glDateTo" />
                </flux:field>
            </div>

            @if (! $this->glAccountId)
                <p class="text-sm text-zinc-500 py-8 text-center">Select an account to view its general ledger.</p>
            @elseif ($this->glLines->isEmpty())
                <p class="text-sm text-zinc-500 py-4 text-center">No posted entries for the selected filters.</p>
            @else
                <div class="flex justify-end mb-3">
                    <flux:button variant="ghost" size="sm" wire:click="exportGlCsv" icon="arrow-down-tray">
                        Export CSV
                    </flux:button>
                </div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Date</flux:table.column>
                        <flux:table.column>Description</flux:table.column>
                        <flux:table.column>Vendor</flux:table.column>
                        <flux:table.column>Debit</flux:table.column>
                        <flux:table.column>Credit</flux:table.column>
                        <flux:table.column>Balance</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->glLines as $line)
                            <flux:table.row wire:key="gl-{{ $line->id }}">
                                <flux:table.cell>
                                    <span class="text-sm text-zinc-500">{{ \Carbon\Carbon::parse($line->date)->format('M j, Y') }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <p class="font-medium text-sm">{{ $line->description }}</p>
                                    @if ($line->memo)
                                        <p class="text-xs text-zinc-500">{{ $line->memo }}</p>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="text-sm text-zinc-500">{{ $line->vendor_name ?? '—' }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($line->debit > 0)
                                        <span class="font-mono text-sm">${{ number_format($line->debit / 100, 2) }}</span>
                                    @else
                                        <span class="text-zinc-300">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($line->credit > 0)
                                        <span class="font-mono text-sm">${{ number_format($line->credit / 100, 2) }}</span>
                                    @else
                                        <span class="text-zinc-300">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-mono text-sm {{ $line->running_balance >= 0 ? '' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $line->running_balance >= 0 ? '' : '-' }}${{ number_format(abs($line->running_balance) / 100, 2) }}
                                    </span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    {{-- Trial Balance --}}
    @if ($activeTab === 'trial')
        <flux:card>
            @if ($this->trialBalanceRows->isEmpty())
                <p class="text-sm text-zinc-500 py-8 text-center">No posted entries for the selected period.</p>
            @else
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="md">Trial Balance</flux:heading>
                    @if ($this->trialBalanceIsBalanced)
                        <flux:badge color="green">✓ Balanced</flux:badge>
                    @else
                        <flux:badge color="red">Out of Balance</flux:badge>
                    @endif
                </div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Account</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column>Debit</flux:table.column>
                        <flux:table.column>Credit</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->trialBalanceRows as $row)
                            <flux:table.row>
                                <flux:table.cell>
                                    <span class="text-zinc-500 text-xs mr-2">{{ $row['code'] }}</span>
                                    {{ $row['name'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="text-xs text-zinc-500 capitalize">{{ str_replace('_', ' ', $row['type']) }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['total_debit'] > 0)
                                        <span class="font-mono text-sm">${{ number_format($row['total_debit'] / 100, 2) }}</span>
                                    @else
                                        <span class="text-zinc-300">—</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['total_credit'] > 0)
                                        <span class="font-mono text-sm">${{ number_format($row['total_credit'] / 100, 2) }}</span>
                                    @else
                                        <span class="text-zinc-300">—</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                        <flux:table.row class="font-semibold border-t-2 border-zinc-300 dark:border-zinc-600">
                            <flux:table.cell colspan="2">Totals</flux:table.cell>
                            <flux:table.cell>
                                <span class="font-mono">${{ number_format($this->trialBalanceTotalDebit / 100, 2) }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="font-mono">${{ number_format($this->trialBalanceTotalCredit / 100, 2) }}</span>
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    {{-- Statement of Financial Position (Balance Sheet) --}}
    @if ($activeTab === 'balance-sheet')
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="md">Statement of Financial Position</flux:heading>
                <div class="flex items-center gap-3">
                    @if ($this->bsIsBalanced)
                        <flux:badge color="green" size="sm">✓ Assets = Net Assets</flux:badge>
                    @endif
                    <flux:field class="flex items-center gap-2 m-0">
                        <flux:label class="text-sm whitespace-nowrap">As of</flux:label>
                        <flux:input type="date" wire:model.live="bsAsOfDate" size="sm" />
                    </flux:field>
                </div>
            </div>

            <div class="space-y-6">
                {{-- Assets --}}
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide mb-2">Assets</h3>
                    @if ($this->bsAssetRows->isEmpty())
                        <p class="text-sm text-zinc-500 py-2 text-center">No posted asset entries as of this date.</p>
                    @else
                        <div class="space-y-1">
                            @foreach ($this->bsAssetRows as $row)
                                <div class="flex justify-between py-1 text-sm">
                                    <span>
                                        <span class="text-zinc-400 mr-2">{{ $row['code'] }}</span>
                                        {{ $row['name'] }}
                                    </span>
                                    <span class="font-mono">
                                        {{ $row['balance'] >= 0 ? '' : '-' }}${{ number_format(abs($row['balance']) / 100, 2) }}
                                    </span>
                                </div>
                            @endforeach
                            <div class="flex justify-between py-1 text-sm font-semibold border-t border-zinc-200 dark:border-zinc-700 mt-2 pt-2">
                                <span>Total Assets</span>
                                <span class="font-mono">${{ number_format($this->bsTotalAssets / 100, 2) }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Net Assets --}}
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide mb-2">Net Assets</h3>
                    <div class="space-y-1">
                        <div class="flex justify-between py-1 text-sm">
                            <span>Net Assets — Unrestricted</span>
                            <span class="font-mono">${{ number_format($this->bsNetAssetsUnrestricted / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between py-1 text-sm">
                            <span>Net Assets — Restricted</span>
                            <span class="font-mono">${{ number_format($this->bsNetAssetsRestricted / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between py-1 text-sm font-semibold border-t border-zinc-200 dark:border-zinc-700 mt-2 pt-2">
                            <span>Total Net Assets</span>
                            <span class="font-mono">${{ number_format($this->bsTotalNetAssets / 100, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- Statement of Cash Flows --}}
    @if ($activeTab === 'cash-flow')
        <flux:card>
            <flux:heading size="md" class="mb-4">Statement of Cash Flows</flux:heading>
            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide mb-2">Operating Activities</h3>
                    <div class="space-y-1">
                        <div class="flex justify-between py-1 text-sm">
                            <span>Cash Received (Revenue)</span>
                            <span class="font-mono text-green-600 dark:text-green-400">+${{ number_format($this->cashInflows / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between py-1 text-sm">
                            <span>Cash Paid (Expenses)</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-${{ number_format($this->cashOutflows / 100, 2) }}</span>
                        </div>
                        <div class="flex justify-between py-1 text-sm font-semibold border-t border-zinc-200 dark:border-zinc-700 mt-2 pt-2">
                            <span>Net Change in Cash</span>
                            <span class="font-mono {{ $this->netCashChange >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $this->netCashChange >= 0 ? '+' : '-' }}${{ number_format(abs($this->netCashChange) / 100, 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- Budget vs. Actual Variance --}}
    @if ($activeTab === 'variance')
        @php
            $variancePeriods = $this->variancePeriods;
            $varianceAccounts = $this->varianceAccounts;
            $varianceBudgetData = $this->varianceBudgetData;
        @endphp
        <flux:card>
            @if ($variancePeriods->isEmpty() || $varianceAccounts->isEmpty())
                <p class="text-sm text-zinc-500 py-8 text-center">Select a fiscal year to view the Budget vs. Actual report.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="text-left py-2 px-3 font-medium text-zinc-600 dark:text-zinc-400 min-w-48">Account</th>
                                @foreach ($variancePeriods as $vPeriod)
                                    <th class="text-right py-2 px-2 font-medium text-zinc-600 dark:text-zinc-400 min-w-24 text-xs" colspan="3">
                                        {{ $vPeriod->start_date->format('M y') }}
                                    </th>
                                @endforeach
                            </tr>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                <th class="py-1 px-3 text-xs text-zinc-500"></th>
                                @foreach ($variancePeriods as $vPeriod)
                                    <th class="py-1 px-1 text-xs text-right text-zinc-500 min-w-16">Budget</th>
                                    <th class="py-1 px-1 text-xs text-right text-zinc-500 min-w-16">Actual</th>
                                    <th class="py-1 px-1 text-xs text-right text-zinc-500 min-w-16">Var</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php $prevType = null; @endphp
                            @foreach ($varianceAccounts as $vAccount)
                                @if ($prevType !== $vAccount->type)
                                    <tr>
                                        <td colspan="{{ count($variancePeriods) * 3 + 1 }}" class="py-1 px-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50">
                                            {{ ucfirst($vAccount->type) }}
                                        </td>
                                    </tr>
                                    @php $prevType = $vAccount->type; @endphp
                                @endif
                                <tr class="border-b border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                    <td class="py-1.5 px-3 text-zinc-700 dark:text-zinc-300">
                                        <span class="text-xs text-zinc-400 mr-1">{{ $vAccount->code }}</span>
                                        {{ $vAccount->name }}
                                    </td>
                                    @foreach ($variancePeriods as $vPeriod)
                                        @php
                                            $vBudget   = $varianceBudgetData[$vAccount->id][$vPeriod->id] ?? 0;
                                            $vActual   = $this->varianceActualForAccount($vAccount, $vPeriod->id);
                                            $vVariance = $vActual - $vBudget;
                                            $vFavorable = ($vAccount->type === 'revenue')
                                                ? $vVariance >= 0
                                                : $vVariance <= 0;
                                        @endphp
                                        <td class="py-1.5 px-1 text-right text-xs font-mono">
                                            @if ($vBudget > 0)
                                                ${{ number_format($vBudget / 100, 2) }}
                                            @else
                                                <span class="text-zinc-300">—</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 px-1 text-right text-xs font-mono">
                                            @if ($vActual !== 0)
                                                ${{ number_format($vActual / 100, 2) }}
                                            @else
                                                <span class="text-zinc-300">—</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 px-1 text-right text-xs font-mono {{ $vFavorable ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            @if ($vVariance !== 0 || $vBudget > 0 || $vActual !== 0)
                                                {{ $vVariance >= 0 ? '+' : '' }}${{ number_format($vVariance / 100, 2) }}
                                            @else
                                                <span class="text-zinc-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <flux:text variant="subtle" class="text-xs mt-3">
                    Actual amounts reflect posted journal entries only. Drafts excluded.
                    Green = favorable (revenue ≥ budget, expenses ≤ budget). Red = unfavorable.
                </flux:text>
            @endif
        </flux:card>
    @endif
</div>
