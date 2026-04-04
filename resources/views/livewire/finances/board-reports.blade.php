<?php

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

        $this->startMonth = now()->startOfYear()->format('Y-m');
        $this->endMonth = now()->format('Y-m');
    }

    public function applyTrimester(int $trimester): void
    {
        $this->authorize('financials-manage');

        $year = now()->year;

        if ($trimester === 1) {
            $this->startMonth = $year.'-01';
            $this->endMonth = $year.'-04';
        } elseif ($trimester === 2) {
            $this->startMonth = $year.'-05';
            $this->endMonth = $year.'-08';
        } else {
            $this->startMonth = $year.'-09';
            $this->endMonth = $year.'-12';
        }
    }

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

    public function rangeLabel(): string
    {
        $start = Carbon::createFromFormat('Y-m', $this->startMonth)->format('F Y');
        $end = Carbon::createFromFormat('Y-m', $this->endMonth)->format('F Y');

        if ($this->startMonth === $this->endMonth) {
            return $start;
        }

        return $start.' – '.$end;
    }
}; ?>

<div class="space-y-6">

    <flux:heading size="xl">Board Reports</flux:heading>

    {{-- Date range controls --}}
    <flux:card class="space-y-4">
        <flux:heading size="sm">Income Statement</flux:heading>

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
                <flux:button size="sm" wire:click="applyTrimester(1)">T1 (Jan–Apr)</flux:button>
                <flux:button size="sm" wire:click="applyTrimester(2)">T2 (May–Aug)</flux:button>
                <flux:button size="sm" wire:click="applyTrimester(3)">T3 (Sep–Dec)</flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Income Statement Report --}}
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

        {{-- Summary cards --}}
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

        {{-- Income breakdown --}}
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

        {{-- Expense breakdown --}}
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

</div>
