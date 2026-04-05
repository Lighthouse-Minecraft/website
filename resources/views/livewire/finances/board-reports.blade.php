<?php

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public string $startMonth = '';
    public string $endMonth = '';

    public function mount(): void
    {
        $this->authorize('financials-manage');

        $fyStartYear = now()->month >= 10 ? now()->year : now()->year - 1;
        $this->startMonth = $fyStartYear.'-10';
        $this->endMonth = now()->format('Y-m');
    }

    public function applyTrimester(int $trimester): void
    {
        $this->authorize('financials-manage');

        // Fiscal year starts in October. T1: Oct–Jan, T2: Feb–May, T3: Jun–Sep.
        $fyStartYear = now()->month >= 10 ? now()->year : now()->year - 1;
        $calYear = $fyStartYear + 1;

        if ($trimester === 1) {
            $this->startMonth = $fyStartYear.'-10';
            $this->endMonth = $calYear.'-01';
        } elseif ($trimester === 2) {
            $this->startMonth = $calYear.'-02';
            $this->endMonth = $calYear.'-05';
        } else {
            $this->startMonth = $calYear.'-06';
            $this->endMonth = $calYear.'-09';
        }
    }

    // ── Income Statement ──────────────────────────────────────────────────────

    public function incomeStatement(): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $this->startMonth)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $this->endMonth)->endOfMonth()->toDateString();

        return $this->buildIncomeStatement($startDate, $endDate);
    }

    private function buildIncomeStatement(string $startDate, string $endDate): array
    {
        $topCategories = FinancialCategory::whereNull('parent_id')
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();

        $incomeCategories = [];
        $expenseCategories = [];

        foreach ($topCategories as $cat) {
            $subcategories = FinancialCategory::where('parent_id', $cat->id)
                ->where('is_archived', false)
                ->orderBy('sort_order')
                ->get();

            $subcategoryData = [];
            $catDirectAmount = (int) FinancialTransaction::where('financial_category_id', $cat->id)
                ->where('type', $cat->type)
                ->whereBetween('transacted_at', [$startDate, $endDate])
                ->sum('amount');

            $catTotal = $catDirectAmount;

            foreach ($subcategories as $sub) {
                $subAmount = (int) FinancialTransaction::where('financial_category_id', $sub->id)
                    ->where('type', $cat->type)
                    ->whereBetween('transacted_at', [$startDate, $endDate])
                    ->sum('amount');

                if ($subAmount > 0) {
                    $subcategoryData[] = ['name' => $sub->name, 'amount' => $subAmount];
                    $catTotal += $subAmount;
                }
            }

            if ($catTotal > 0) {
                $row = [
                    'name' => $cat->name,
                    'total' => $catTotal,
                    'subcategories' => $subcategoryData,
                ];

                if ($cat->type === 'income') {
                    $incomeCategories[] = $row;
                } else {
                    $expenseCategories[] = $row;
                }
            }
        }

        $totalIncome = collect($incomeCategories)->sum('total');
        $totalExpense = collect($expenseCategories)->sum('total');

        return [
            'income' => $incomeCategories,
            'expense' => $expenseCategories,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netIncome' => $totalIncome - $totalExpense,
        ];
    }

    // ── Balance Sheet ─────────────────────────────────────────────────────────

    public function balanceSheet(): array
    {
        $asOfDate = Carbon::createFromFormat('Y-m', $this->endMonth)->endOfMonth()->toDateString();

        return $this->buildBalanceSheet($asOfDate);
    }

    private function buildBalanceSheet(string $asOfDate): array
    {
        $accounts = FinancialAccount::where('is_archived', false)->orderBy('name')->get();

        $accountBalances = $accounts->map(function ($account) use ($asOfDate) {
            $credits = (int) $account->transactions()->where('type', 'income')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');
            $debits = (int) $account->transactions()->where('type', 'expense')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');
            $transfersOut = (int) $account->transactions()->where('type', 'transfer')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');
            $transfersIn = (int) $account->incomingTransfers()->where('type', 'transfer')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');

            return [
                'name' => $account->name,
                'balance' => $account->opening_balance + $credits - $debits - $transfersOut + $transfersIn,
            ];
        })->values()->toArray();

        $netAssets = collect($accountBalances)->sum('balance');

        return [
            'accounts' => $accountBalances,
            'netAssets' => $netAssets,
        ];
    }

    // ── Cash Flow Statement ───────────────────────────────────────────────────

    public function cashFlow(): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $this->startMonth)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $this->endMonth)->endOfMonth()->toDateString();

        return $this->buildCashFlow($startDate, $endDate);
    }

    private function buildCashFlow(string $startDate, string $endDate): array
    {
        // Operating: income and expense by top-level category
        $is = $this->buildIncomeStatement($startDate, $endDate);

        // Financing: inter-account transfers
        $transfers = FinancialTransaction::where('type', 'transfer')
            ->whereBetween('transacted_at', [$startDate, $endDate])
            ->with(['account', 'targetAccount'])
            ->orderBy('transacted_at')
            ->get()
            ->map(function ($tx) {
                return [
                    'date' => $tx->transacted_at,
                    'from' => $tx->account?->name ?? 'Unknown',
                    'to' => $tx->targetAccount?->name ?? 'Unknown',
                    'amount' => $tx->amount,
                ];
            })
            ->values()
            ->toArray();

        return [
            'operating' => [
                'income' => $is['income'],
                'expense' => $is['expense'],
                'totalIncome' => $is['totalIncome'],
                'totalExpense' => $is['totalExpense'],
                'net' => $is['netIncome'],
            ],
            'financing' => $transfers,
            'netChange' => $is['netIncome'],
        ];
    }

    // ── Labels ────────────────────────────────────────────────────────────────

    public function rangeLabel(): string
    {
        $start = Carbon::createFromFormat('Y-m', $this->startMonth)->format('F Y');
        $end = Carbon::createFromFormat('Y-m', $this->endMonth)->format('F Y');

        if ($this->startMonth === $this->endMonth) {
            return $start;
        }

        return $start.' – '.$end;
    }

    public function asOfLabel(): string
    {
        return Carbon::createFromFormat('Y-m', $this->endMonth)->endOfMonth()->format('F j, Y');
    }
}; ?>

<div class="space-y-6">

    {{-- Finance Navigation --}}
    <div class="flex flex-wrap gap-2">
        <flux:button href="{{ route('finances.dashboard') }}" wire:navigate size="sm" icon="banknotes">Dashboard</flux:button>
        <flux:button href="{{ route('finances.budget') }}" wire:navigate size="sm" icon="calculator">Budget</flux:button>
        <flux:button href="{{ route('finances.reports') }}" wire:navigate size="sm" icon="document-text">Period Reports</flux:button>
        @can('financials-manage')
            <flux:button href="{{ route('finances.board-reports') }}" wire:navigate size="sm" icon="chart-bar">Board Reports</flux:button>
            <flux:button href="{{ route('finances.accounts') }}" wire:navigate size="sm" icon="building-library">Accounts</flux:button>
            <flux:button href="{{ route('finances.categories') }}" wire:navigate size="sm" icon="tag">Categories</flux:button>
        @endcan
    </div>

    <flux:heading size="xl">Board Reports</flux:heading>

    {{-- Date range controls --}}
    <flux:card class="space-y-4">
        <flux:heading size="sm">Date Range</flux:heading>

        <div class="flex flex-wrap gap-4 items-end">
            <flux:field>
                <flux:label>Start Month</flux:label>
                <flux:input type="month" wire:model.live="startMonth" />
            </flux:field>

            <flux:field>
                <flux:label>End Month</flux:label>
                <flux:input type="month" wire:model.live="endMonth" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button size="sm" wire:click="applyTrimester(1)">T1 (Oct–Jan)</flux:button>
                <flux:button size="sm" wire:click="applyTrimester(2)">T2 (Feb–May)</flux:button>
                <flux:button size="sm" wire:click="applyTrimester(3)">T3 (Jun–Sep)</flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Income Statement --}}
    @php $report = $this->incomeStatement(); @endphp

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Income Statement — {{ $this->rangeLabel() }}</flux:heading>
            <flux:button
                href="{{ route('finances.board-reports.income-statement.pdf', ['start' => $startMonth, 'end' => $endMonth]) }}"
                target="_blank"
                icon="arrow-down-tray"
                size="sm"
            >
                Download PDF
            </flux:button>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <flux:card class="text-center">
                <flux:text variant="subtle" class="text-sm">Total Income</flux:text>
                <flux:heading size="lg" class="text-green-500">${{ number_format($report['totalIncome'] / 100, 2) }}</flux:heading>
            </flux:card>
            <flux:card class="text-center">
                <flux:text variant="subtle" class="text-sm">Total Expenses</flux:text>
                <flux:heading size="lg" class="text-red-500">${{ number_format($report['totalExpense'] / 100, 2) }}</flux:heading>
            </flux:card>
            <flux:card class="text-center">
                <flux:text variant="subtle" class="text-sm">Net Income</flux:text>
                <flux:heading size="lg" class="{{ $report['netIncome'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                    {{ $report['netIncome'] >= 0 ? '+' : '' }}${{ number_format(abs($report['netIncome']) / 100, 2) }}
                </flux:heading>
            </flux:card>
        </div>

        @if (!empty($report['income']))
            <div>
                <flux:heading size="sm" class="mb-2">Income</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Category</flux:table.column>
                        <flux:table.column class="text-right">Amount</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($report['income'] as $cat)
                            <flux:table.row class="font-medium">
                                <flux:table.cell>{{ $cat['name'] }}</flux:table.cell>
                                <flux:table.cell class="text-right text-green-500">${{ number_format($cat['total'] / 100, 2) }}</flux:table.cell>
                            </flux:table.row>
                            @foreach ($cat['subcategories'] as $sub)
                                <flux:table.row class="text-sm">
                                    <flux:table.cell class="pl-8 text-zinc-500">{{ $sub['name'] }}</flux:table.cell>
                                    <flux:table.cell class="text-right text-zinc-500">${{ number_format($sub['amount'] / 100, 2) }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        @endforeach
                        <flux:table.row class="font-bold border-t">
                            <flux:table.cell>Total Income</flux:table.cell>
                            <flux:table.cell class="text-right text-green-500">${{ number_format($report['totalIncome'] / 100, 2) }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>
        @else
            <flux:text variant="subtle">No income recorded in this period.</flux:text>
        @endif

        @if (!empty($report['expense']))
            <div>
                <flux:heading size="sm" class="mb-2">Expenses</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Category</flux:table.column>
                        <flux:table.column class="text-right">Amount</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($report['expense'] as $cat)
                            <flux:table.row class="font-medium">
                                <flux:table.cell>{{ $cat['name'] }}</flux:table.cell>
                                <flux:table.cell class="text-right text-red-500">${{ number_format($cat['total'] / 100, 2) }}</flux:table.cell>
                            </flux:table.row>
                            @foreach ($cat['subcategories'] as $sub)
                                <flux:table.row class="text-sm">
                                    <flux:table.cell class="pl-8 text-zinc-500">{{ $sub['name'] }}</flux:table.cell>
                                    <flux:table.cell class="text-right text-zinc-500">${{ number_format($sub['amount'] / 100, 2) }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        @endforeach
                        <flux:table.row class="font-bold border-t">
                            <flux:table.cell>Total Expenses</flux:table.cell>
                            <flux:table.cell class="text-right text-red-500">${{ number_format($report['totalExpense'] / 100, 2) }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>
        @else
            <flux:text variant="subtle">No expenses recorded in this period.</flux:text>
        @endif

        @if (!empty($report['income']) || !empty($report['expense']))
            <div class="flex justify-end pt-2">
                <div class="text-lg font-bold {{ $report['netIncome'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                    Net Income: {{ $report['netIncome'] >= 0 ? '+' : '' }}${{ number_format(abs($report['netIncome']) / 100, 2) }}
                </div>
            </div>
        @endif
    </div>

    {{-- Balance Sheet --}}
    @php $bs = $this->balanceSheet(); @endphp

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Balance Sheet — As of {{ $this->asOfLabel() }}</flux:heading>
            <flux:button
                href="{{ route('finances.board-reports.balance-sheet.pdf', ['end' => $endMonth]) }}"
                target="_blank"
                icon="arrow-down-tray"
                size="sm"
            >
                Download PDF
            </flux:button>
        </div>

        @if (!empty($bs['accounts']))
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Account</flux:table.column>
                    <flux:table.column class="text-right">Balance</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($bs['accounts'] as $ab)
                        <flux:table.row>
                            <flux:table.cell>{{ $ab['name'] }}</flux:table.cell>
                            <flux:table.cell class="text-right {{ $ab['balance'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                ${{ number_format($ab['balance'] / 100, 2) }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                    <flux:table.row class="font-bold border-t">
                        <flux:table.cell>Net Assets</flux:table.cell>
                        <flux:table.cell class="text-right {{ $bs['netAssets'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            ${{ number_format($bs['netAssets'] / 100, 2) }}
                        </flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        @else
            <flux:text variant="subtle">No accounts found.</flux:text>
        @endif
    </div>

    {{-- Cash Flow Statement --}}
    @php $cf = $this->cashFlow(); @endphp

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Cash Flow Statement — {{ $this->rangeLabel() }}</flux:heading>
            <flux:button
                href="{{ route('finances.board-reports.cash-flow.pdf', ['start' => $startMonth, 'end' => $endMonth]) }}"
                target="_blank"
                icon="arrow-down-tray"
                size="sm"
            >
                Download PDF
            </flux:button>
        </div>

        <div>
            <flux:heading size="sm" class="mb-2">Operating Activities</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Description</flux:table.column>
                    <flux:table.column class="text-right">Amount</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($cf['operating']['income'] as $cat)
                        <flux:table.row>
                            <flux:table.cell>{{ $cat['name'] }} (income)</flux:table.cell>
                            <flux:table.cell class="text-right text-green-500">${{ number_format($cat['total'] / 100, 2) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                    @foreach ($cf['operating']['expense'] as $cat)
                        <flux:table.row>
                            <flux:table.cell>{{ $cat['name'] }} (expense)</flux:table.cell>
                            <flux:table.cell class="text-right text-red-500">-${{ number_format($cat['total'] / 100, 2) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                    <flux:table.row class="font-bold border-t">
                        <flux:table.cell>Net from Operations</flux:table.cell>
                        <flux:table.cell class="text-right {{ $cf['operating']['net'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $cf['operating']['net'] >= 0 ? '+' : '' }}${{ number_format(abs($cf['operating']['net']) / 100, 2) }}
                        </flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        </div>

        @if (!empty($cf['financing']))
            <div>
                <flux:heading size="sm" class="mb-2">Financing Activities (Transfers)</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Date</flux:table.column>
                        <flux:table.column>From</flux:table.column>
                        <flux:table.column>To</flux:table.column>
                        <flux:table.column class="text-right">Amount</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($cf['financing'] as $tx)
                            <flux:table.row>
                                <flux:table.cell>{{ \Illuminate\Support\Carbon::parse($tx['date'])->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $tx['from'] }}</flux:table.cell>
                                <flux:table.cell>{{ $tx['to'] }}</flux:table.cell>
                                <flux:table.cell class="text-right">${{ number_format($tx['amount'] / 100, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @else
            <flux:text variant="subtle" class="text-sm">No inter-account transfers in this period.</flux:text>
        @endif

        <div class="flex justify-end pt-2">
            <div class="text-lg font-bold {{ $cf['netChange'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                Net Cash Change: {{ $cf['netChange'] >= 0 ? '+' : '' }}${{ number_format(abs($cf['netChange']) / 100, 2) }}
            </div>
        </div>
    </div>

</div>
