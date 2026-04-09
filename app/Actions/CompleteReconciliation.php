<?php

namespace App\Actions;

use App\Models\FinancialReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteReconciliation
{
    use AsAction;

    /**
     * Finalize a bank reconciliation.
     *
     * Validates that the statement ending balance matches the sum of cleared journal
     * entry lines (debit − credit = net effect on the bank account). Marks the
     * reconciliation as completed on success.
     *
     * @throws \RuntimeException if the reconciliation is already completed or the difference is non-zero.
     */
    public function handle(FinancialReconciliation $reconciliation, User $user): void
    {
        if ($reconciliation->status === 'completed') {
            throw new \RuntimeException('This reconciliation is already completed.');
        }

        $clearedBalance = (int) DB::table('financial_reconciliation_lines as rl')
            ->join('financial_journal_entry_lines as jel', 'jel.id', '=', 'rl.journal_entry_line_id')
            ->where('rl.reconciliation_id', $reconciliation->id)
            ->selectRaw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as net')
            ->value('net');

        $prior = FinancialReconciliation::where('account_id', $reconciliation->account_id)
            ->where('status', 'completed')
            ->whereHas('period', fn ($q) => $q->where('end_date', '<', $reconciliation->period->start_date))
            ->orderByDesc('id')
            ->first();

        $openingBalance = $prior?->statement_ending_balance ?? 0;

        $difference = $reconciliation->statement_ending_balance - ($openingBalance + $clearedBalance);

        if ($difference !== 0) {
            throw new \RuntimeException(
                'Cannot complete reconciliation: difference is not zero (difference: '.
                number_format(abs($difference) / 100, 2).').'
            );
        }

        $reconciliation->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by_id' => $user->id,
        ]);

        RecordActivity::run(
            $reconciliation->account,
            'reconciliation_completed',
            "Bank reconciliation completed for {$reconciliation->account->name} — {$reconciliation->period->name} by {$user->name}."
        );
    }
}
