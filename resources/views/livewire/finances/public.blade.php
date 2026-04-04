<?php

use App\Enums\MembershipLevel;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public function publishedMonths(): array
    {
        $reports = FinancialPeriodReport::whereNotNull('published_at')
            ->orderBy('month', 'desc')
            ->get();

        $user = auth()->user();
        $isResident = $user && $user->isAtLeastLevel(MembershipLevel::Resident);
        $isFinancialsView = $user && $user->can('financials-view');

        $months = [];
        foreach ($reports as $report) {
            $monthStart = Carbon::parse($report->month)->toDateString();
            $monthEnd = Carbon::parse($report->month)->endOfMonth()->toDateString();
            $ym = Carbon::parse($report->month)->format('Y-m');

            $income = (int) FinancialTransaction::where('type', 'income')
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $expense = (int) FinancialTransaction::where('type', 'expense')
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $row = [
                'ym' => $ym,
                'label' => Carbon::parse($report->month)->format('F Y'),
                'monthStart' => $monthStart,
                'monthEnd' => $monthEnd,
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
                'categories' => [],
            ];

            if ($isResident) {
                $row['categories'] = $this->categoryBreakdown($monthStart, $monthEnd, $isFinancialsView);
            }

            $months[] = $row;
        }

        return $months;
    }

    private function categoryBreakdown(string $monthStart, string $monthEnd, bool $withSubcategories): array
    {
        $topCategories = FinancialCategory::whereNull('parent_id')
            ->where('is_archived', false)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        $result = [];

        foreach ($topCategories as $cat) {
            if ($withSubcategories) {
                $subcategories = FinancialCategory::where('parent_id', $cat->id)
                    ->where('is_archived', false)
                    ->orderBy('sort_order')
                    ->get();

                $subcategoryData = [];
                $catDirectCount = FinancialTransaction::where('financial_category_id', $cat->id)
                    ->where('type', $cat->type)
                    ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                    ->count();
                $catDirectAmount = (int) FinancialTransaction::where('financial_category_id', $cat->id)
                    ->where('type', $cat->type)
                    ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                    ->sum('amount');

                $catTotal = $catDirectAmount;
                $catCount = $catDirectCount;

                foreach ($subcategories as $sub) {
                    $subAmount = (int) FinancialTransaction::where('financial_category_id', $sub->id)
                        ->where('type', $cat->type)
                        ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                        ->sum('amount');
                    $subCount = FinancialTransaction::where('financial_category_id', $sub->id)
                        ->where('type', $cat->type)
                        ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                        ->count();

                    if ($subAmount > 0 || $subCount > 0) {
                        $subcategoryData[] = [
                            'name' => $sub->name,
                            'amount' => $subAmount,
                            'count' => $subCount,
                        ];
                        $catTotal += $subAmount;
                        $catCount += $subCount;
                    }
                }

                if ($catTotal > 0) {
                    $result[] = [
                        'name' => $cat->name,
                        'type' => $cat->type,
                        'total' => $catTotal,
                        'count' => $catCount,
                        'subcategories' => $subcategoryData,
                    ];
                }
            } else {
                // Resident view: top-level only, no subcategory detail
                $subIds = FinancialCategory::where('parent_id', $cat->id)->pluck('id');
                $ids = $subIds->prepend($cat->id);

                $total = (int) FinancialTransaction::whereIn('financial_category_id', $ids)
                    ->where('type', $cat->type)
                    ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                    ->sum('amount');

                if ($total > 0) {
                    $result[] = [
                        'name' => $cat->name,
                        'type' => $cat->type,
                        'total' => $total,
                        'count' => null,
                        'subcategories' => [],
                    ];
                }
            }
        }

        return $result;
    }

    public function yearToDate(): array
    {
        $year = now()->year;

        // Only sum months that are published
        $publishedMonths = FinancialPeriodReport::whereNotNull('published_at')
            ->whereYear('month', $year)
            ->get();

        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($publishedMonths as $report) {
            $monthStart = Carbon::parse($report->month)->toDateString();
            $monthEnd = Carbon::parse($report->month)->endOfMonth()->toDateString();

            $totalIncome += (int) FinancialTransaction::where('type', 'income')
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $totalExpense += (int) FinancialTransaction::where('type', 'expense')
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->sum('amount');
        }

        return [
            'income' => $totalIncome,
            'expense' => $totalExpense,
            'net' => $totalIncome - $totalExpense,
        ];
    }

    public function viewTier(): string
    {
        $user = auth()->user();
        if ($user && $user->can('financials-view')) {
            return 'staff';
        }
        if ($user && $user->isAtLeastLevel(MembershipLevel::Resident)) {
            return 'resident';
        }

        return 'public';
    }
}; ?>

<div class="space-y-8">

    <div>
        <flux:heading size="xl">Finances</flux:heading>
        <flux:text variant="subtle">Published financial summaries for Lighthouse Minecraft Ministry.</flux:text>
    </div>

    {{-- Year-to-date summary --}}
    @php $ytd = $this->yearToDate(); $tier = $this->viewTier(); @endphp

    <flux:card class="space-y-2">
        <flux:heading size="sm">{{ now()->year }} Year-to-Date (Published Months)</flux:heading>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <flux:text variant="subtle" class="text-xs">Total Income</flux:text>
                <div class="text-lg font-semibold text-green-500">${{ number_format($ytd['income'] / 100, 2) }}</div>
            </div>
            <div>
                <flux:text variant="subtle" class="text-xs">Total Expenses</flux:text>
                <div class="text-lg font-semibold text-red-500">${{ number_format($ytd['expense'] / 100, 2) }}</div>
            </div>
            <div>
                <flux:text variant="subtle" class="text-xs">Net</flux:text>
                <div class="text-lg font-semibold {{ $ytd['net'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                    {{ $ytd['net'] >= 0 ? '+' : '' }}${{ number_format(abs($ytd['net']) / 100, 2) }}
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Monthly breakdown --}}
    @php $months = $this->publishedMonths(); @endphp

    @forelse ($months as $month)
        <flux:card class="space-y-4" wire:key="month-{{ $month['ym'] }}">
            <div>
                <flux:heading size="md">{{ $month['label'] }}</flux:heading>
                <div class="grid grid-cols-3 gap-4 mt-2">
                    <div>
                        <flux:text variant="subtle" class="text-xs">Income</flux:text>
                        <div class="font-semibold text-green-500">${{ number_format($month['income'] / 100, 2) }}</div>
                    </div>
                    <div>
                        <flux:text variant="subtle" class="text-xs">Expenses</flux:text>
                        <div class="font-semibold text-red-500">${{ number_format($month['expense'] / 100, 2) }}</div>
                    </div>
                    <div>
                        <flux:text variant="subtle" class="text-xs">Net</flux:text>
                        <div class="font-semibold {{ $month['net'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $month['net'] >= 0 ? '+' : '' }}${{ number_format(abs($month['net']) / 100, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            @if (!empty($month['categories']))
                <div class="space-y-3">
                    @foreach ($month['categories'] as $cat)
                        <div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-medium">{{ $cat['name'] }}</span>
                                <span class="{{ $cat['type'] === 'income' ? 'text-green-500' : 'text-red-500' }}">
                                    ${{ number_format($cat['total'] / 100, 2) }}
                                </span>
                            </div>

                            @if (!empty($cat['subcategories']))
                                <div class="pl-4 mt-1 space-y-1">
                                    @foreach ($cat['subcategories'] as $sub)
                                        <div class="flex justify-between items-center text-xs text-zinc-500">
                                            <span>
                                                {{ $sub['name'] }}
                                                @if ($tier === 'staff')
                                                    <span class="text-zinc-400">({{ $sub['count'] }} {{ $sub['count'] === 1 ? 'transaction' : 'transactions' }})</span>
                                                @endif
                                            </span>
                                            <span>${{ number_format($sub['amount'] / 100, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    @empty
        <flux:text variant="subtle">No published financial reports yet.</flux:text>
    @endforelse

</div>
