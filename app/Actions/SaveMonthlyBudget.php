<?php

namespace App\Actions;

use App\Models\MonthlyBudget;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveMonthlyBudget
{
    use AsAction;

    /**
     * @param  array<int, int>  $plannedAmounts  category_id => planned_amount_in_cents
     */
    public function handle(string $monthStart, array $plannedAmounts): void
    {
        foreach ($plannedAmounts as $categoryId => $plannedAmount) {
            if ($plannedAmount < 0) {
                continue;
            }

            MonthlyBudget::updateOrCreate(
                [
                    'financial_category_id' => $categoryId,
                    'month' => $monthStart,
                ],
                [
                    'planned_amount' => $plannedAmount,
                ]
            );
        }
    }
}
