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
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Financial Reports</flux:heading>
            <flux:text variant="subtle">Statement of Activities, General Ledger, and Trial Balance. All figures are based on posted entries only.</flux:text>
        </div>
    </div>

    {{-- Tabs --}}
    <flux:tabs wire:model="activeTab">
        <flux:tab name="activities">Statement of Activities</flux:tab>
        <flux:tab name="ledger">General Ledger</flux:tab>
        <flux:tab name="trial">Trial Balance</flux:tab>
    </flux:tabs>

    {{-- Shared filters (FY/period/date) for Activities and Trial Balance --}}
    @if ($activeTab !== 'ledger')
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
</div>
