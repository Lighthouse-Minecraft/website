<?php

use App\Actions\PublishPeriodReport;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\MonthlyBudget;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    // ── Publish modal state ───────────────────────────────────────────────────
    public ?string $publishMonth = null;

    // ── Report list ───────────────────────────────────────────────────────────

    public function months(): array
    {
        // Collect all distinct months that have at least one transaction
        $months = FinancialTransaction::selectRaw("strftime('%Y-%m', transacted_at) as ym")
            ->groupByRaw("strftime('%Y-%m', transacted_at)")
            ->orderByRaw("strftime('%Y-%m', transacted_at) DESC")
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

    // ── Pre-publish summary ───────────────────────────────────────────────────

    public function summaryForMonth(string $monthStart): array
    {
        $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();

        $income = (int) FinancialTransaction::where('type', 'income')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $expense = (int) FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

        // Account balances as of end of month
        $accounts = FinancialAccount::where('is_archived', false)
            ->orderBy('name')
            ->get();

        $accountBalances = $accounts->map(function ($account) use ($monthEnd) {
            $credits = (int) $account->transactions()->where('type', 'income')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');
            $debits = (int) $account->transactions()->where('type', 'expense')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');
            $transfersOut = (int) $account->transactions()->where('type', 'transfer')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');
            $transfersIn = (int) $account->incomingTransfers()->where('type', 'transfer')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');
            $balance = $account->opening_balance + $credits - $debits - $transfersOut + $transfersIn;

            return ['name' => $account->name, 'balance' => $balance];
        });

        // Budget variance per top-level category
        $categories = FinancialCategory::whereNull('parent_id')
            ->where('is_archived', false)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        $budgetVariances = [];
        foreach ($categories as $cat) {
            $planned = (int) optional(MonthlyBudget::whereDate('month', $monthStart)
                ->where('financial_category_id', $cat->id)
                ->first())->planned_amount;

            $subIds = FinancialCategory::where('parent_id', $cat->id)->pluck('id');
            $ids = $subIds->prepend($cat->id);
            $actual = (int) FinancialTransaction::whereIn('financial_category_id', $ids)
                ->whereBetween('transacted_at', [$monthStart, $monthEnd])
                ->whereIn('type', ['income', 'expense'])
                ->sum('amount');

            if ($planned > 0 || $actual > 0) {
                $budgetVariances[] = [
                    'name' => $cat->name,
                    'type' => $cat->type,
                    'planned' => $planned,
                    'actual' => $actual,
                    'variance' => $planned - $actual,
                ];
            }
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'accountBalances' => $accountBalances,
            'budgetVariances' => $budgetVariances,
        ];
    }

    public function openPublishModal(string $monthStart): void
    {
        $this->authorize('financials-treasurer');
        $this->publishMonth = $monthStart;
        Flux::modal('publish-report-modal')->show();
    }

    public function confirmPublish(): void
    {
        $this->authorize('financials-treasurer');

        if ($this->publishMonth === null) {
            return;
        }

        try {
            PublishPeriodReport::run($this->publishMonth, auth()->user());
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');

            return;
        }

        Flux::modal('publish-report-modal')->close();
        Flux::toast('Period report published.', 'Success', variant: 'success');
        $this->publishMonth = null;
    }
}; ?>

<div class="space-y-6">

    <flux:heading size="xl">Period Reports</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Month</flux:table.column>
            <flux:table.column>Total Income</flux:table.column>
            <flux:table.column>Total Expenses</flux:table.column>
            <flux:table.column>Net Change</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            @can('financials-treasurer')
                <flux:table.column>Actions</flux:table.column>
            @endcan
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->months() as $row)
                <flux:table.row wire:key="report-{{ $row['ym'] }}">
                    <flux:table.cell>{{ $row['label'] }}</flux:table.cell>
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
                    @can('financials-treasurer')
                        <flux:table.cell>
                            @unless ($row['published'])
                                <flux:button size="sm" wire:click="openPublishModal('{{ $row['monthStart'] }}')">
                                    Publish Report
                                </flux:button>
                            @endunless
                        </flux:table.cell>
                    @endcan
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

    {{-- Publish Modal --}}
    @can('financials-treasurer')
        <flux:modal name="publish-report-modal" class="w-full max-w-2xl space-y-5">
            @if ($publishMonth !== null)
                @php $summary = $this->summaryForMonth($publishMonth); @endphp

                <flux:heading size="lg">Publish Report — {{ \Illuminate\Support\Carbon::parse($publishMonth)->format('F Y') }}</flux:heading>

                <flux:text variant="subtle">Review this summary before publishing. Once published, all transactions in this month become read-only.</flux:text>

                <div class="grid grid-cols-3 gap-4">
                    <flux:card class="text-center">
                        <flux:text variant="subtle" class="text-sm">Total Income</flux:text>
                        <flux:heading size="lg" class="text-green-500">${{ number_format($summary['income'] / 100, 2) }}</flux:heading>
                    </flux:card>
                    <flux:card class="text-center">
                        <flux:text variant="subtle" class="text-sm">Total Expenses</flux:text>
                        <flux:heading size="lg" class="text-red-500">${{ number_format($summary['expense'] / 100, 2) }}</flux:heading>
                    </flux:card>
                    <flux:card class="text-center">
                        <flux:text variant="subtle" class="text-sm">Net Change</flux:text>
                        <flux:heading size="lg" class="{{ $summary['net'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $summary['net'] >= 0 ? '+' : '' }}${{ number_format(abs($summary['net']) / 100, 2) }}
                        </flux:heading>
                    </flux:card>
                </div>

                @if ($summary['accountBalances']->isNotEmpty())
                    <div>
                        <flux:heading size="sm" class="mb-2">Account Balances (as of end of month)</flux:heading>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($summary['accountBalances'] as $ab)
                                <div class="flex justify-between text-sm">
                                    <span>{{ $ab['name'] }}</span>
                                    <span>${{ number_format($ab['balance'] / 100, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($summary['budgetVariances']))
                    <div>
                        <flux:heading size="sm" class="mb-2">Budget Variance</flux:heading>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Category</flux:table.column>
                                <flux:table.column>Planned</flux:table.column>
                                <flux:table.column>Actual</flux:table.column>
                                <flux:table.column>Variance</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($summary['budgetVariances'] as $bv)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $bv['name'] }}</flux:table.cell>
                                        <flux:table.cell>{{ $bv['planned'] > 0 ? '$' . number_format($bv['planned'] / 100, 2) : '—' }}</flux:table.cell>
                                        <flux:table.cell>{{ $bv['actual'] > 0 ? '$' . number_format($bv['actual'] / 100, 2) : '—' }}</flux:table.cell>
                                        <flux:table.cell>
                                            @php $v = $bv['variance']; @endphp
                                            <span class="{{ $v >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                                {{ $v >= 0 ? '+' : '' }}${{ number_format(abs($v) / 100, 2) }}
                                            </span>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif

                <div class="flex gap-3 pt-2">
                    <flux:button wire:click="confirmPublish" variant="primary" icon="lock-closed">Confirm & Publish</flux:button>
                    <flux:button x-on:click="$flux.modal('publish-report-modal').close()" variant="ghost">Cancel</flux:button>
                </div>
            @endif
        </flux:modal>
    @endcan

</div>
