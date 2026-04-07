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
    public ?int $pendingClosePeriodId = null;

    public function mount(): void
    {
        $this->authorize('finance-view');

        if (auth()->user()->can('finance-manage')) {
            GenerateFinancialPeriods::generateForCurrentFY();
        }
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

    public function getNextFyYearProperty(): int
    {
        return $this->currentFyYear + 1;
    }

    public function getNextFyAlreadyExistsProperty(): bool
    {
        return FinancialPeriod::where('fiscal_year', $this->nextFyYear)->exists();
    }

    public function getCanGenerateNextFyProperty(): bool
    {
        // Allow generating next FY periods when we are in the last 2 months of the current FY
        // (month_number 11 or 12 of the FY, i.e. 2 months before year end).
        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        $currentCalendarMonth = now()->month;
        $fyMonthNumber = (($currentCalendarMonth - $startMonth + 12) % 12) + 1;

        return $fyMonthNumber >= 11;
    }

    public function generateNextFy(): void
    {
        $this->authorize('finance-manage');

        $startMonth = (int) SiteConfig::getValue('finance_fy_start_month', '10');
        GenerateFinancialPeriods::run($this->nextFyYear, $startMonth);

        Flux::modal('confirm-generate-next-fy')->close();
        Flux::toast("Periods for FY {$this->nextFyYear} have been generated.", 'Done', variant: 'success');
    }

    public function showCloseConfirm(int $periodId): void
    {
        $this->authorize('finance-manage');
        $this->pendingClosePeriodId = $periodId;
        Flux::modal('confirm-close-period')->show();
    }

    public function closePeriod(): void
    {
        $this->authorize('finance-manage');

        if (! $this->pendingClosePeriodId) {
            return;
        }

        $period = FinancialPeriod::findOrFail($this->pendingClosePeriodId);
        $this->pendingClosePeriodId = null;
        Flux::modal('confirm-close-period')->close();

        try {
            CloseFinancialPeriod::run($period, auth()->user());
            Flux::toast("Period {$period->name} has been closed.", 'Period Closed', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Cannot Close Period', variant: 'danger');
        } catch (\Throwable $e) {
            report($e);
            Flux::toast('An unexpected error occurred while closing this period.', 'Cannot Close Period', variant: 'danger');
        }
    }
}; ?>

<div class="space-y-8">
    @include('livewire.finance.partials.nav')

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Fiscal Periods</flux:heading>
            <flux:text variant="subtle">Monthly fiscal periods for the accounting ledger. Close a period after bank reconciliations are complete.</flux:text>
        </div>
        @can('finance-manage')
            @if (! $this->nextFyAlreadyExists)
                <flux:modal.trigger name="confirm-generate-next-fy">
                    <flux:button
                        variant="outline"
                        :disabled="! $this->canGenerateNextFy"
                        title="{{ $this->canGenerateNextFy ? 'Generate periods for FY ' . $this->nextFyYear : 'Available in the last 2 months of FY ' . $this->currentFyYear }}"
                    >
                        Generate FY {{ $this->nextFyYear }} Periods
                    </flux:button>
                </flux:modal.trigger>
            @endif
        @endcan
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
                                            <div wire:key="period-{{ $period->id }}-account-{{ $account->id }}" class="flex items-center gap-2 text-xs">
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
                                @can('finance-manage')
                                    @if ($period->status !== 'closed')
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="showCloseConfirm({{ $period->id }})"
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
                    <flux:card wire:key="prior-fy-{{ $fyYear }}">
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

    {{-- Close Period Confirmation Modal --}}
    <flux:modal name="confirm-close-period" class="max-w-sm">
        <flux:heading size="lg" class="mb-2">Close This Period?</flux:heading>
        <flux:text class="mb-4">This will generate closing entries and permanently lock the period. No new entries can be posted to a closed period.</flux:text>
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="closePeriod">Close Period</flux:button>
        </div>
    </flux:modal>

    {{-- Generate Next FY Confirmation Modal --}}
    <flux:modal name="confirm-generate-next-fy" class="max-w-sm">
        <flux:heading size="lg" class="mb-2">Generate FY {{ $this->nextFyYear }} Periods?</flux:heading>
        <flux:text class="mb-4">This will create the 12 monthly fiscal periods for FY {{ $this->nextFyYear }}. You can start recording transactions for those months immediately.</flux:text>
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="generateNextFy">Generate Periods</flux:button>
        </div>
    </flux:modal>
</div>
