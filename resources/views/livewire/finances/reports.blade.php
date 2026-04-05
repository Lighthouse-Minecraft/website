<?php

use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    // ── Report list ───────────────────────────────────────────────────────────

    public function months(): array
    {
        $isTreasurer = auth()->user()?->can('financials-treasurer') ?? false;

        // For view-only users, show only published months
        if (! $isTreasurer) {
            $publishedMonths = FinancialPeriodReport::whereNotNull('published_at')
                ->orderBy('month', 'desc')
                ->get();

            $result = [];
            foreach ($publishedMonths as $report) {
                $monthStart = Carbon::parse($report->month)->toDateString();
                $monthEnd = Carbon::parse($report->month)->endOfMonth()->toDateString();
                $ym = Carbon::parse($report->month)->format('Y-m');

                $income = (int) FinancialTransaction::where('type', 'income')
                    ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                    ->sum('amount');

                $expense = (int) FinancialTransaction::where('type', 'expense')
                    ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                    ->sum('amount');

                $result[] = [
                    'ym' => $ym,
                    'label' => Carbon::parse($report->month)->format('F Y'),
                    'monthStart' => $monthStart,
                    'income' => $income,
                    'expense' => $expense,
                    'net' => $income - $expense,
                    'published' => true,
                    'publishedAt' => $report->published_at,
                ];
            }

            return $result;
        }

        // Collect all distinct months that have at least one transaction
        $ymExpr = match (DB::getDriverName()) {
            'pgsql' => "to_char(transacted_at, 'YYYY-MM')",
            'mysql' => "DATE_FORMAT(transacted_at, '%Y-%m')",
            default => "strftime('%Y-%m', transacted_at)",
        };

        $months = FinancialTransaction::selectRaw("{$ymExpr} as ym")
            ->groupByRaw($ymExpr)
            ->orderByRaw("{$ymExpr} DESC")
            ->pluck('ym');

        $reports = FinancialPeriodReport::all()->keyBy(
            fn ($r) => Carbon::parse($r->month)->format('Y-m')
        );

        $result = [];
        foreach ($months as $ym) {
            $monthStart = Carbon::createFromFormat('Y-m', $ym)->startOfMonth()->toDateString();
            $monthEnd = Carbon::createFromFormat('Y-m', $ym)->endOfMonth()->toDateString();

            $income = (int) FinancialTransaction::where('type', 'income')
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $expense = (int) FinancialTransaction::where('type', 'expense')
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $report = $reports->get($ym);

            $result[] = [
                'ym' => $ym,
                'label' => Carbon::createFromFormat('Y-m', $ym)->format('F Y'),
                'monthStart' => $monthStart,
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
                'published' => $report?->isPublished() ?? false,
                'publishedAt' => $report?->published_at,
            ];
        }

        return $result;
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

    <flux:heading size="xl">Period Reports</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Month</flux:table.column>
            <flux:table.column>Total Income</flux:table.column>
            <flux:table.column>Total Expenses</flux:table.column>
            <flux:table.column>Net Change</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->months() as $row)
                <flux:table.row wire:key="report-{{ $row['ym'] }}">
                    <flux:table.cell>
                        <a href="{{ route('finances.reports.show', ['month' => $row['ym']]) }}"
                            wire:navigate
                            class="text-blue-600 hover:underline dark:text-blue-400">
                            {{ $row['label'] }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell class="text-green-500">${{ number_format($row['income'] / 100, 2) }}</flux:table.cell>
                    <flux:table.cell class="text-red-500">${{ number_format($row['expense'] / 100, 2) }}</flux:table.cell>
                    <flux:table.cell>
                        <span class="{{ $row['net'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $row['net'] >= 0 ? '+' : '' }}${{ number_format(abs($row['net']) / 100, 2) }}
                        </span>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($row['published'])
                            <flux:badge variant="success">Published</flux:badge>
                        @else
                            <flux:badge variant="zinc">Unpublished</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            @if ($row['published'])
                                <flux:button size="sm" icon="arrow-down-tray" href="{{ route('finances.reports.pdf', ['month' => $row['ym']]) }}" target="_blank">PDF</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text variant="subtle">No transactions recorded yet.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</div>
