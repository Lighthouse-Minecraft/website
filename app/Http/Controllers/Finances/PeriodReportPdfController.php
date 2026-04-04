<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\MonthlyBudget;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class PeriodReportPdfController extends Controller
{
    public function __invoke(string $month): Response
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $report = FinancialPeriodReport::whereDate('month', $monthStart)->first();

        abort_unless($report && $report->isPublished(), 404);

        $label = Carbon::createFromFormat('Y-m', $month)->format('F Y');

        $summary = $this->buildSummary($monthStart, $monthEnd);

        $pdf = Pdf::loadView('finances.period-report-pdf', [
            'label' => $label,
            'publishedAt' => $report->published_at,
            'summary' => $summary,
        ]);

        $filename = 'period-report-'.$month.'.pdf';

        return $pdf->download($filename);
    }

    private function buildSummary(string $monthStart, string $monthEnd): array
    {
        $income = (int) FinancialTransaction::where('type', 'income')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $expense = (int) FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->sum('amount');

        // Account balances as of end of month
        $accounts = FinancialAccount::where('is_archived', false)->orderBy('name')->get();

        $accountBalances = $accounts->map(function ($account) use ($monthEnd) {
            $credits = (int) $account->transactions()->where('type', 'income')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');
            $debits = (int) $account->transactions()->where('type', 'expense')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');
            $transfersOut = (int) $account->transactions()->where('type', 'transfer')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');
            $transfersIn = (int) $account->incomingTransfers()->where('type', 'transfer')
                ->where('transacted_at', '<=', $monthEnd)->sum('amount');

            return [
                'name' => $account->name,
                'balance' => $account->opening_balance + $credits - $debits - $transfersOut + $transfersIn,
            ];
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

        // Income by category
        $incomeByCategory = FinancialTransaction::where('type', 'income')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->whereNotNull('financial_category_id')
            ->with('category.parent')
            ->get()
            ->groupBy('financial_category_id')
            ->map(function ($txs) {
                $cat = $txs->first()->category;
                $name = $cat?->parent ? $cat->parent->name.' / '.$cat->name : ($cat?->name ?? 'Uncategorized');

                return ['name' => $name, 'amount' => $txs->sum('amount')];
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();

        // Expense by category
        $expenseByCategory = FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$monthStart, $monthEnd])
            ->whereNotNull('financial_category_id')
            ->with('category.parent')
            ->get()
            ->groupBy('financial_category_id')
            ->map(function ($txs) {
                $cat = $txs->first()->category;
                $name = $cat?->parent ? $cat->parent->name.' / '.$cat->name : ($cat?->name ?? 'Uncategorized');

                return ['name' => $name, 'amount' => $txs->sum('amount')];
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'accountBalances' => $accountBalances,
            'budgetVariances' => $budgetVariances,
            'incomeByCategory' => $incomeByCategory,
            'expenseByCategory' => $expenseByCategory,
        ];
    }
}
