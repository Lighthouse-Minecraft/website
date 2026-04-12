<?php

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;

class DonationController extends Controller
{
    public function index()
    {
        $closedPeriods = FinancialPeriod::where('status', 'closed')
            ->orderByDesc('fiscal_year')
            ->orderByDesc('start_date')
            ->limit(2)
            ->get()
            ->map(function (FinancialPeriod $period) {
                $income = (int) DB::table('financial_journal_entry_lines as jel')
                    ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                    ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
                    ->where('je.period_id', $period->id)
                    ->where('je.status', 'posted')
                    ->whereNot('je.entry_type', 'closing')
                    ->where('fa.type', 'revenue')
                    ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total')
                    ->value('total');

                $expenses = (int) DB::table('financial_journal_entry_lines as jel')
                    ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                    ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
                    ->where('je.period_id', $period->id)
                    ->where('je.status', 'posted')
                    ->whereNot('je.entry_type', 'closing')
                    ->where('fa.type', 'expense')
                    ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total')
                    ->value('total');

                return [
                    'name' => $period->name,
                    'income' => $income,
                    'expenses' => $expenses,
                    'net' => $income - $expenses,
                ];
            });

        return view('donation.index', compact('closedPeriods'));
    }
}
