<?php

namespace App\Actions;

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishPeriodReport
{
    use AsAction;

    public function handle(string $monthStart, User $publishedBy): FinancialPeriodReport
    {
        // Reject double-publish
        $existing = FinancialPeriodReport::whereDate('month', $monthStart)->first();
        if ($existing && $existing->isPublished()) {
            throw new \RuntimeException('This month has already been published.');
        }

        // Require at least one transaction
        $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();
        $count = FinancialTransaction::whereBetween('transacted_at', [$monthStart, $monthEnd])->count();
        if ($count === 0) {
            throw new \RuntimeException('Cannot publish a month with no transactions.');
        }

        $snapshot = $this->computeSnapshot($monthStart, $monthEnd);

        return FinancialPeriodReport::updateOrCreate(
            ['month' => $monthStart],
            [
                'published_at' => now(),
                'published_by' => $publishedBy->id,
                'summary_snapshot' => $snapshot,
            ]
        );
    }

    private function computeSnapshot(string $monthStart, string $monthEnd): array
    {
        $income = (int) FinancialTransaction::where('type', 'income')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $expense = (int) FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

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
        })->values()->all();

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
}
