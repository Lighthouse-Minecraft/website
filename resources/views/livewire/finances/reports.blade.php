<?php

use App\Actions\PublishPeriodReport;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\MonthlyBudget;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    // ── Publish modal state ───────────────────────────────────────────────────
    #[Locked]
    public ?string $publishMonth = null;

    // ── View detail modal state ───────────────────────────────────────────────
    #[Locked]
    public ?string $viewMonth = null;

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

    // ── Pre-publish summary ───────────────────────────────────────────────────

    public function summaryForMonth(string $monthStart): array
    {
        // For published months, return the immutable snapshot stored at publish time.
        $report = FinancialPeriodReport::whereDate('month', $monthStart)
            ->whereNotNull('published_at')
            ->first();

        if ($report && $report->summary_snapshot !== null) {
            $snap = $report->summary_snapshot;
            // Ensure accountBalances is a Collection for template compatibility.
            $snap['accountBalances'] = collect($snap['accountBalances']);

            return $snap;
        }

        $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();

        $income = (int) FinancialTransaction::where('type', 'income')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $expense = (int) FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

        // Account balances as of end of month — include all accounts regardless of archived
        // status so that archiving an account later doesn't silently drop it from historical reports
        $accounts = FinancialAccount::orderBy('name')->get();

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

        // Budget variance per top-level category — include all categories regardless of archived
        // status so that archiving a category later doesn't silently drop it from historical reports
        $categories = FinancialCategory::whereNull('parent_id')
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
                    // For expenses: positive variance = under budget (good). For income: positive = beat target (good).
                    'variance' => $cat->type === 'income'
                        ? $actual - $planned
                        : $planned - $actual,
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

    public function openViewModal(string $monthStart): void
    {
        $this->authorize('financials-view');

        $isPublished = FinancialPeriodReport::whereDate('month', $monthStart)
            ->whereNotNull('published_at')
            ->exists();

        if (! $isPublished) {
            abort(404);
        }

        $this->viewMonth = $monthStart;
        Flux::modal('view-report-modal')->show();
    }

    public function closeViewModal(): void
    {
        $this->viewMonth = null;
        Flux::modal('view-report-modal')->close();
    }

    public function openPublishModal(string $monthStart): void
    {
        $this->authorize('financials-treasurer');
        $this->publishMonth = $monthStart;
        Flux::modal('publish-report-modal')->show();
    }

    public function closePublishModal(): void
    {
        $this->publishMonth = null;
        Flux::modal('publish-report-modal')->close();
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
                    <flux:table.cell>
                        <div class="flex gap-2">
                            @if ($row['published'])
                                <flux:button size="sm" icon="eye" wire:click="openViewModal('{{ $row['monthStart'] }}')">View</flux:button>
                                <flux:button size="sm" icon="arrow-down-tray" href="{{ route('finances.reports.pdf', ['month' => $row['ym']]) }}" target="_blank">PDF</flux:button>
                            @else
                                @can('financials-treasurer')
                                    <flux:button size="sm" wire:click="openPublishModal('{{ $row['monthStart'] }}')">Publish</flux:button>
                                @endcan
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

    {{-- View Report Modal --}}
    <flux:modal name="view-report-modal" class="w-full max-w-2xl space-y-5">
        @if ($viewMonth !== null)
            @php $viewSummary = $this->summaryForMonth($viewMonth); @endphp

            <flux:heading size="lg">
                Period Report — {{ \Illuminate\Support\Carbon::parse($viewMonth)->format('F Y') }}
            </flux:heading>

            <div class="grid grid-cols-3 gap-4">
                <flux:card class="text-center">
                    <flux:text variant="subtle" class="text-sm">Total Income</flux:text>
                    <flux:heading size="lg" class="text-green-500">${{ number_format($viewSummary['income'] / 100, 2) }}</flux:heading>
                </flux:card>
                <flux:card class="text-center">
                    <flux:text variant="subtle" class="text-sm">Total Expenses</flux:text>
                    <flux:heading size="lg" class="text-red-500">${{ number_format($viewSummary['expense'] / 100, 2) }}</flux:heading>
                </flux:card>
                <flux:card class="text-center">
                    <flux:text variant="subtle" class="text-sm">Net Change</flux:text>
                    <flux:heading size="lg" class="{{ $viewSummary['net'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                        {{ $viewSummary['net'] >= 0 ? '+' : '' }}${{ number_format(abs($viewSummary['net']) / 100, 2) }}
                    </flux:heading>
                </flux:card>
            </div>

            @if ($viewSummary['accountBalances']->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-2">Account Balances</flux:heading>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($viewSummary['accountBalances'] as $ab)
                            <div class="flex justify-between text-sm">
                                <span>{{ $ab['name'] }}</span>
                                <span>${{ number_format($ab['balance'] / 100, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!empty($viewSummary['budgetVariances']))
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
                            @foreach ($viewSummary['budgetVariances'] as $bv)
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
                <flux:button href="{{ route('finances.reports.pdf', ['month' => \Illuminate\Support\Carbon::parse($viewMonth)->format('Y-m')]) }}" target="_blank" icon="arrow-down-tray">Download PDF</flux:button>
                <flux:button wire:click="closeViewModal" variant="ghost">Close</flux:button>
            </div>
        @endif
    </flux:modal>

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
                    <flux:button wire:click="closePublishModal" variant="ghost">Cancel</flux:button>
                </div>
            @endif
        </flux:modal>
    @endcan

</div>
