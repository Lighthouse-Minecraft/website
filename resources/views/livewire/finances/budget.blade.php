<?php

use App\Actions\SaveMonthlyBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\MonthlyBudget;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public string $month = '';

    /** @var array<int, string> category_id => planned_amount_in_cents (as string for input) */
    public array $plannedAmounts = [];

    public function mount(string $month = ''): void
    {
        $this->month = $month !== '' ? $month : now()->format('Y-m');
        $this->loadBudget();
    }

    public function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)
            ->subMonth()
            ->format('Y-m');
        $this->loadBudget();
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)
            ->addMonth()
            ->format('Y-m');
        $this->loadBudget();
    }

    public function loadBudget(): void
    {
        $monthStart = $this->monthStart()->toDateString();
        $categories = $this->categories();

        // Initialize all to 0
        $amounts = [];
        foreach ($categories as $category) {
            $amounts[$category->id] = '';
        }

        // Load existing budget rows for this month
        $existing = MonthlyBudget::whereDate('month', $monthStart)
            ->whereIn('financial_category_id', $categories->pluck('id'))
            ->get()
            ->keyBy('financial_category_id');

        if ($existing->isNotEmpty()) {
            foreach ($existing as $row) {
                $amounts[$row->financial_category_id] = (string) $row->planned_amount;
            }
        } else {
            // Pre-fill from previous month
            $prevMonthStart = Carbon::parse($monthStart)->subMonth()->toDateString();
            $prev = MonthlyBudget::whereDate('month', $prevMonthStart)
                ->whereIn('financial_category_id', $categories->pluck('id'))
                ->get()
                ->keyBy('financial_category_id');

            foreach ($prev as $row) {
                $amounts[$row->financial_category_id] = (string) $row->planned_amount;
            }
        }

        $this->plannedAmounts = $amounts;
    }

    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialCategory::whereNull('parent_id')
            ->where('is_archived', false)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Actual amount (cents) for a top-level category in the selected month,
     * including all its subcategories.
     */
    public function actualForCategory(int $categoryId): int
    {
        $subIds = FinancialCategory::where('parent_id', $categoryId)->pluck('id');
        $ids = $subIds->prepend($categoryId);

        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return (int) FinancialTransaction::whereIn('financial_category_id', $ids)
            ->whereBetween('transacted_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereIn('type', ['income', 'expense'])
            ->sum('amount');
    }

    /**
     * The three most recently published FinancialPeriodReport month date strings.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function publishedMonths(): \Illuminate\Support\Collection
    {
        return FinancialPeriodReport::whereNotNull('published_at')
            ->orderBy('month', 'desc')
            ->limit(3)
            ->pluck('month')
            ->map(fn ($m) => $m instanceof Carbon ? $m->toDateString() : (string) $m);
    }

    /**
     * 3-month rolling average of actual spending for a category across published months.
     * Returns null when no published months exist.
     */
    public function trendForCategory(int $categoryId): ?int
    {
        $months = $this->publishedMonths();

        if ($months->isEmpty()) {
            return null;
        }

        $subIds = FinancialCategory::where('parent_id', $categoryId)->pluck('id');
        $ids = $subIds->prepend($categoryId);

        $total = 0;
        foreach ($months as $monthStart) {
            $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();
            $total += (int) FinancialTransaction::whereIn('financial_category_id', $ids)
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->whereIn('type', ['income', 'expense'])
                ->sum('amount');
        }

        return (int) round($total / $months->count());
    }

    public function budgetRows(): array
    {
        $rows = [];
        foreach ($this->categories() as $category) {
            $planned = isset($this->plannedAmounts[$category->id]) && $this->plannedAmounts[$category->id] !== ''
                ? (int) $this->plannedAmounts[$category->id]
                : 0;
            $actual = $this->actualForCategory($category->id);
            $variance = $planned - $actual;
            $trend = $this->trendForCategory($category->id);

            $rows[] = [
                'category' => $category,
                'planned' => $planned,
                'actual' => $actual,
                'variance' => $variance,
                'trend' => $trend,
            ];
        }

        return $rows;
    }

    public function saveBudget(): void
    {
        $this->authorize('financials-treasurer');

        $monthStart = $this->monthStart()->toDateString();

        $amounts = [];
        foreach ($this->plannedAmounts as $categoryId => $value) {
            $amounts[(int) $categoryId] = $value !== '' ? (int) $value : 0;
        }

        SaveMonthlyBudget::run($monthStart, $amounts);

        Flux::toast('Budget saved.', 'Success', variant: 'success');
    }
}; ?>

<div class="space-y-6">

    <div class="flex items-center justify-between">
        <flux:heading size="xl">Monthly Budget</flux:heading>
        <div class="flex items-center gap-3">
            <flux:button wire:click="previousMonth" icon="chevron-left" variant="ghost" size="sm" />
            <flux:heading size="lg">{{ $this->monthStart()->format('F Y') }}</flux:heading>
            <flux:button wire:click="nextMonth" icon="chevron-right" variant="ghost" size="sm" />
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Category</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Planned ($)</flux:table.column>
            <flux:table.column>Actual ($)</flux:table.column>
            <flux:table.column>Variance ($)</flux:table.column>
            <flux:table.column>3-Mo Trend ($)</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->budgetRows() as $row)
                <flux:table.row wire:key="budget-{{ $row['category']->id }}">
                    <flux:table.cell>{{ $row['category']->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge variant="{{ $row['category']->type === 'income' ? 'success' : 'danger' }}">
                            {{ ucfirst($row['category']->type) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('financials-treasurer')
                            <flux:input
                                wire:model="plannedAmounts.{{ $row['category']->id }}"
                                type="number"
                                min="0"
                                class="w-32"
                                placeholder="0"
                            />
                        @else
                            {{ $row['planned'] > 0 ? '$' . number_format($row['planned'] / 100, 2) : '—' }}
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $row['actual'] > 0 ? '$' . number_format($row['actual'] / 100, 2) : '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($row['planned'] > 0 || $row['actual'] > 0)
                            @php $v = $row['variance']; @endphp
                            <span class="{{ $v >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $v >= 0 ? '+' : '' }}${{ number_format(abs($v) / 100, 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($row['trend'] !== null)
                            ${{ number_format($row['trend'] / 100, 2) }}
                        @else
                            —
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @can('financials-treasurer')
        <div class="flex justify-end">
            <flux:button wire:click="saveBudget" variant="primary" icon="check">Save Budget</flux:button>
        </div>
    @endcan

</div>
