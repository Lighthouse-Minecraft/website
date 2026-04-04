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

class CashFlowPdfController extends Controller
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

        $cashFlow = $this->buildCashFlow($startDate, $endDate);

        $startLabel = Carbon::createFromFormat('Y-m', $start)->format('F Y');
        $endLabel = Carbon::createFromFormat('Y-m', $end)->format('F Y');
        $label = $start === $end ? $startLabel : $startLabel.' – '.$endLabel;

        $pdf = Pdf::loadView('finances.cash-flow-pdf', [
            'label' => $label,
            'cashFlow' => $cashFlow,
        ]);

        return $pdf->download('cash-flow-'.$start.'-to-'.$end.'.pdf');
    }

    private function buildCashFlow(string $startDate, string $endDate): array
    {
        $topCategories = FinancialCategory::whereNull('parent_id')
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->get();

        $incomeCategories = [];
        $expenseCategories = [];

        foreach ($topCategories as $cat) {
            $subIds = FinancialCategory::where('parent_id', $cat->id)->pluck('id');
            $ids = $subIds->prepend($cat->id);

            $amount = (int) FinancialTransaction::whereIn('financial_category_id', $ids)
                ->where('type', $cat->type)
                ->whereBetween('transacted_at', [$startDate, $endDate])
                ->sum('amount');

            if ($amount > 0) {
                if ($cat->type === 'income') {
                    $incomeCategories[] = ['name' => $cat->name, 'amount' => $amount];
                } else {
                    $expenseCategories[] = ['name' => $cat->name, 'amount' => $amount];
                }
            }
        }

        $totalIncome = collect($incomeCategories)->sum('amount');
        $totalExpense = collect($expenseCategories)->sum('amount');

        $transfers = FinancialTransaction::where('type', 'transfer')
            ->whereBetween('transacted_at', [$startDate, $endDate])
            ->with(['account', 'targetAccount'])
            ->orderBy('transacted_at')
            ->get()
            ->map(function ($tx) {
                return [
                    'date' => Carbon::parse($tx->transacted_at)->format('M j, Y'),
                    'from' => $tx->account?->name ?? 'Unknown',
                    'to' => $tx->targetAccount?->name ?? 'Unknown',
                    'amount' => $tx->amount,
                ];
            })
            ->values()
            ->toArray();

        return [
            'income' => $incomeCategories,
            'expense' => $expenseCategories,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netOperating' => $totalIncome - $totalExpense,
            'transfers' => $transfers,
            'netChange' => $totalIncome - $totalExpense,
        ];
    }
}
