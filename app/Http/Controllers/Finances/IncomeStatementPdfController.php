<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class IncomeStatementPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Gate::allows('financials-manage'), 403);

        $request->validate([
            'start' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'end' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $start = $request->input('start');
        $end = $request->input('end');

        $startDate = Carbon::createFromFormat('Y-m', $start)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $end)->endOfMonth()->toDateString();

        $report = $this->buildIncomeStatement($startDate, $endDate);

        $startLabel = Carbon::createFromFormat('Y-m', $start)->format('F Y');
        $endLabel = Carbon::createFromFormat('Y-m', $end)->format('F Y');
        $label = $start === $end ? $startLabel : $startLabel.' – '.$endLabel;

        $pdf = Pdf::loadView('finances.income-statement-pdf', [
            'label' => $label,
            'report' => $report,
        ]);

        $filename = 'income-statement-'.$start.'-to-'.$end.'.pdf';

        return $pdf->download($filename);
    }

    private function buildIncomeStatement(string $startDate, string $endDate): array
    {
        $topCategories = FinancialCategory::whereNull('parent_id')
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();

        $incomeCategories = [];
        $expenseCategories = [];

        foreach ($topCategories as $cat) {
            $subcategories = FinancialCategory::where('parent_id', $cat->id)
                ->where('is_archived', false)
                ->orderBy('sort_order')
                ->get();

            $subcategoryData = [];
            $catDirectAmount = (int) FinancialTransaction::where('financial_category_id', $cat->id)
                ->where('type', $cat->type)
                ->whereBetween('transacted_at', [$startDate, $endDate])
                ->sum('amount');

            $catTotal = $catDirectAmount;

            foreach ($subcategories as $sub) {
                $subAmount = (int) FinancialTransaction::where('financial_category_id', $sub->id)
                    ->where('type', $cat->type)
                    ->whereBetween('transacted_at', [$startDate, $endDate])
                    ->sum('amount');

                if ($subAmount > 0) {
                    $subcategoryData[] = ['name' => $sub->name, 'amount' => $subAmount];
                    $catTotal += $subAmount;
                }
            }

            if ($catTotal > 0) {
                $row = [
                    'name' => $cat->name,
                    'total' => $catTotal,
                    'subcategories' => $subcategoryData,
                ];

                if ($cat->type === 'income') {
                    $incomeCategories[] = $row;
                } else {
                    $expenseCategories[] = $row;
                }
            }
        }

        $totalIncome = collect($incomeCategories)->sum('total');
        $totalExpense = collect($expenseCategories)->sum('total');

        return [
            'income' => $incomeCategories,
            'expense' => $expenseCategories,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netIncome' => $totalIncome - $totalExpense,
        ];
    }
}
