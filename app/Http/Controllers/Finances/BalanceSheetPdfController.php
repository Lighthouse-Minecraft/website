<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class BalanceSheetPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Gate::allows('financials-manage'), 403);

        $request->validate([
            'end' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $end = $request->input('end');
        $asOfDate = Carbon::createFromFormat('Y-m', $end)->endOfMonth()->toDateString();

        $accounts = FinancialAccount::where('is_archived', false)->orderBy('name')->get();

        $accountBalances = $accounts->map(function ($account) use ($asOfDate) {
            $credits = (int) $account->transactions()->where('type', 'income')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');
            $debits = (int) $account->transactions()->where('type', 'expense')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');
            $transfersOut = (int) $account->transactions()->where('type', 'transfer')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');
            $transfersIn = (int) $account->incomingTransfers()->where('type', 'transfer')
                ->where('transacted_at', '<=', $asOfDate)->sum('amount');

            return [
                'name' => $account->name,
                'balance' => $account->opening_balance + $credits - $debits - $transfersOut + $transfersIn,
            ];
        })->values()->toArray();

        $netAssets = collect($accountBalances)->sum('balance');

        $label = Carbon::createFromFormat('Y-m', $end)->endOfMonth()->format('F j, Y');

        $pdf = Pdf::loadView('finances.balance-sheet-pdf', [
            'label' => $label,
            'accounts' => $accountBalances,
            'netAssets' => $netAssets,
        ]);

        return $pdf->download('balance-sheet-as-of-'.$end.'.pdf');
    }
}
