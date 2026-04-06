<?php

use App\Actions\CloseFinancialPeriod;
use App\Actions\GenerateFinancialPeriods;
use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use App\Models\FinancialReconciliation;
use App\Models\SiteConfig;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->authorize('finance-view');
        GenerateFinancialPeriods::generateForCurrentFY();
    }

    public function getCurrentFyYearProperty(): int
    {
        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        $now = now();

        return ($now->month >= $startMonth) ? $now->year + 1 : $now->year;
    }

    public function getCurrentFyPeriodsProperty()
    {
        return FinancialPeriod::where('fiscal_year', $this->currentFyYear)
            ->orderBy('start_date')
            ->with('closedBy')
            ->get();
    }

    public function getPriorFyPeriodsProperty()
    {
        return FinancialPeriod::where('fiscal_year', '<', $this->currentFyYear)
            ->orderByDesc('fiscal_year')
            ->orderBy('start_date')
            ->with('closedBy')
            ->get()
            ->groupBy('fiscal_year');
    }

    public function getBankAccountsProperty()
    {
        return FinancialAccount::where('is_bank_account', true)->where('is_active', true)->orderBy('code')->get();
    }

    public function getReconciliationStatusProperty(): array
    {
        if ($this->bankAccounts->isEmpty()) {
            return [];
        }

        $recs = FinancialReconciliation::whereIn('account_id', $this->bankAccounts->pluck('id'))
            ->get()
            ->groupBy(fn ($r) => "{$r->account_id}_{$r->period_id}");

        return $recs->toArray();
    }

    public function closePeriod(int $periodId): void
    {
        $this->authorize('finance-record');

        $period = FinancialPeriod::findOrFail($periodId);

        try {
            CloseFinancialPeriod::run($period, auth()->user());
            Flux::toast("Period {$period->name} has been closed.", 'Period Closed', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Cannot Close Period', variant: 'danger');
        }
    }
}; ?>

<div class="space-y-8">
    <div>
        <flux:heading size="xl">Fiscal Periods</flux:heading>
        <flux:text variant="subtle">Monthly fiscal periods for the accounting ledger. Close a period after bank reconciliations are complete.</flux:text>
    </div>

    {{-- Current FY --}}
    <flux:card>
        <flux:heading size="md" class="mb-4">FY {{ $this->currentFyYear }}</flux:heading>

        @if ($this->currentFyPeriods->isEmpty())
            <flux:text variant="subtle" class="py-4 text-center">No periods found for the current fiscal year.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Period</flux:table.column>
                    <flux:table.column>Date Range</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Reconciliation</flux:table.column>
                    <flux:table.column>Closed</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->currentFyPeriods as $period)
                        <flux:table.row wire:key="period-{{ $period->id }}">
                            <flux:table.cell>{{ $period->name }}</flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-500">
                                    {{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($period->status === 'open')
                                    <flux:badge color="green" size="sm">Open</flux:badge>
                                @elseif ($period->status === 'reconciling')
                                    <flux:badge color="yellow" size="sm">Reconciling</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Closed</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($this->bankAccounts->isNotEmpty())
                                    <div class="space-y-1">
                                        @foreach ($this->bankAccounts as $account)
                                            @php
                                                $recKey = "{$account->id}_{$period->id}";
                                                $rec = isset($this->reconciliationStatus[$recKey])
                                                    ? collect($this->reconciliationStatus[$recKey])->first()
                                                    : null;
                                                $recStatus = $rec['status'] ?? null;
                                            @endphp
                                            <div class="flex items-center gap-2 text-xs">
                                                @can('finance-record')
                                                    <a href="{{ route('finance.reconciliation.show', ['accountId' => $account->id, 'periodId' => $period->id]) }}"
                                                       class="text-blue-600 dark:text-blue-400 hover:underline">
                                                        {{ $account->name }}
                                                    </a>
                                                @else
                                                    <span>{{ $account->name }}</span>
                                                @endcan
                                                @if ($recStatus === 'completed')
                                                    <flux:badge color="green" size="sm">✓</flux:badge>
                                                @elseif ($recStatus === 'in_progress')
                                                    <flux:badge color="yellow" size="sm">In Progress</flux:badge>
                                                @else
                                                    <flux:badge color="zinc" size="sm">Not Started</flux:badge>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-zinc-400 text-xs">No bank accounts</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($period->status === 'closed' && $period->closed_at)
                                    <span class="text-sm text-zinc-500">
                                        {{ $period->closed_at->format('M j, Y') }}
                                        @if ($period->closedBy)
                                            by {{ $period->closedBy->name }}
                                        @endif
                                    </span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @can('finance-record')
                                    @if ($period->status !== 'closed')
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="closePeriod({{ $period->id }})"
                                            wire:confirm="Close {{ $period->name }}? This will generate closing entries and lock the period."
                                        >
                                            Close Period
                                        </flux:button>
                                    @endif
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    {{-- Prior FYs (collapsed) --}}
    @if ($this->priorFyPeriods->isNotEmpty())
        <details class="group">
            <summary class="cursor-pointer list-none">
                <flux:button variant="ghost" size="sm" icon="chevron-right" class="group-open:rotate-90 transition-transform">
                    Prior Fiscal Years
                </flux:button>
            </summary>

            <div class="mt-4 space-y-6">
                @foreach ($this->priorFyPeriods as $fyYear => $periods)
                    <flux:card>
                        <flux:heading size="sm" class="mb-3">FY {{ $fyYear }}</flux:heading>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Period</flux:table.column>
                                <flux:table.column>Date Range</flux:table.column>
                                <flux:table.column>Status</flux:table.column>
                                <flux:table.column>Closed</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($periods as $period)
                                    <flux:table.row wire:key="prior-period-{{ $period->id }}">
                                        <flux:table.cell>{{ $period->name }}</flux:table.cell>
                                        <flux:table.cell>
                                            <span class="text-sm text-zinc-500">
                                                {{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }}
                                            </span>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if ($period->status === 'open')
                                                <flux:badge color="green" size="sm">Open</flux:badge>
                                            @elseif ($period->status === 'reconciling')
                                                <flux:badge color="yellow" size="sm">Reconciling</flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm">Closed</flux:badge>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if ($period->status === 'closed' && $period->closed_at)
                                                <span class="text-sm text-zinc-500">
                                                    {{ $period->closed_at->format('M j, Y') }}
                                                    @if ($period->closedBy)
                                                        by {{ $period->closedBy->name }}
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                @endforeach
            </div>
        </details>
    @endif
</div>
