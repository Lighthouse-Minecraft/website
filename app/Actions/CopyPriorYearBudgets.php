<?php

namespace App\Actions;

use App\Models\FinancialBudget;
use App\Models\FinancialPeriod;
use Lorisleiva\Actions\Concerns\AsAction;

class CopyPriorYearBudgets
{
    use AsAction;

    /**
     * Copy all budget amounts from $fromFyYear into $toFyYear.
     * For each account+month in the prior year, creates or updates the matching period in the target year.
     *
     * @return int Number of budget rows copied/updated.
     */
    public function handle(int $fromFyYear, int $toFyYear): int
    {
        $fromPeriods = FinancialPeriod::where('fiscal_year', $fromFyYear)->get()->keyBy('month_number');
        $toPeriods = FinancialPeriod::where('fiscal_year', $toFyYear)->get()->keyBy('month_number');

        if ($fromPeriods->isEmpty() || $toPeriods->isEmpty()) {
            return 0;
        }

        $fromBudgets = FinancialBudget::whereIn('period_id', $fromPeriods->pluck('id'))->get();

        $copied = 0;
        foreach ($fromBudgets as $budget) {
            $fromPeriod = $fromPeriods->first(fn ($p) => $p->id === $budget->period_id);
            if (! $fromPeriod) {
                continue;
            }

            $toPeriod = $toPeriods->get($fromPeriod->month_number);
            if (! $toPeriod) {
                continue;
            }

            FinancialBudget::updateOrCreate(
                ['account_id' => $budget->account_id, 'period_id' => $toPeriod->id],
                ['amount' => $budget->amount]
            );

            $copied++;
        }

        return $copied;
    }
}
