<?php

namespace App\Actions;

use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\FinancialReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CloseFinancialPeriod
{
    use AsAction;

    /**
     * Close a financial period.
     *
     * Validates all bank accounts have completed reconciliations, generates GAAP
     * closing entries (zeroing revenue and expense accounts into Net Assets), marks
     * the period as closed, and logs the activity.
     *
     * @throws \RuntimeException if the period is already closed, any bank account
     *                           lacks a completed reconciliation, or Net Assets
     *                           accounts are missing.
     */
    public function handle(FinancialPeriod $period, User $user): void
    {
        if ($period->status === 'closed') {
            throw new \RuntimeException('This period is already closed.');
        }

        // Validate all bank accounts have completed reconciliations
        $bankAccounts = FinancialAccount::where('is_bank_account', true)
            ->where('is_active', true)
            ->get();

        if ($bankAccounts->isNotEmpty()) {
            $completedIds = FinancialReconciliation::where('period_id', $period->id)
                ->where('status', 'completed')
                ->pluck('account_id');

            $missing = $bankAccounts->whereNotIn('id', $completedIds);

            if ($missing->isNotEmpty()) {
                $names = $missing->pluck('name')->implode(', ');

                throw new \RuntimeException(
                    "Cannot close period: the following accounts still need reconciliation: {$names}."
                );
            }
        }

        // Find Net Assets accounts
        $netAssetsUnrestricted = FinancialAccount::where('type', 'net_assets')
            ->where('subtype', 'unrestricted')
            ->first();

        $netAssetsRestricted = FinancialAccount::where('type', 'net_assets')
            ->where('subtype', 'restricted')
            ->first();

        if (! $netAssetsUnrestricted) {
            throw new \RuntimeException('Cannot close period: Net Assets — Unrestricted account not found.');
        }

        // Calculate posted balances for revenue and expense accounts
        $revenueBalances = $this->getAccountBalances($period, 'revenue');
        $expenseBalances = $this->getAccountBalances($period, 'expense');

        // Generate closing entries
        DB::transaction(function () use ($period, $user, $revenueBalances, $expenseBalances, $netAssetsUnrestricted, $netAssetsRestricted) {
            $this->generateRevenueClosingEntry($period, $user, $revenueBalances, $netAssetsUnrestricted, $netAssetsRestricted);
            $this->generateExpenseClosingEntry($period, $user, $expenseBalances, $netAssetsUnrestricted);

            // Lock the period
            $period->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_id' => $user->id,
            ]);
        });

        RecordActivity::run(
            $period,
            'period_closed',
            "Period {$period->name} closed by {$user->name}."
        );
    }

    /**
     * Get the net posted balance for each account of a given type.
     * Returns [account_id => ['account' => model, 'net' => cents, 'restricted_net' => cents]].
     */
    private function getAccountBalances(FinancialPeriod $period, string $type): array
    {
        $accounts = FinancialAccount::where('type', $type)->where('is_active', true)->get();
        $balances = [];

        foreach ($accounts as $account) {
            // Net unrestricted balance
            $unrestrictedNet = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.account_id', $account->id)
                ->where('je.period_id', $period->id)
                ->where('je.status', 'posted')
                ->whereNot('je.entry_type', 'closing')
                ->whereNull('je.restricted_fund_id')
                ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as net')
                ->value('net');

            // Net restricted balance
            $restrictedNet = (int) DB::table('financial_journal_entry_lines as jel')
                ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.account_id', $account->id)
                ->where('je.period_id', $period->id)
                ->where('je.status', 'posted')
                ->whereNot('je.entry_type', 'closing')
                ->whereNotNull('je.restricted_fund_id')
                ->selectRaw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as net')
                ->value('net');

            $totalNet = $unrestrictedNet + $restrictedNet;

            if ($totalNet !== 0) {
                $balances[$account->id] = [
                    'account' => $account,
                    'net' => $totalNet,
                    'unrestricted_net' => $unrestrictedNet,
                    'restricted_net' => $restrictedNet,
                ];
            }
        }

        return $balances;
    }

    /**
     * Generate the revenue closing entry.
     * Dr each revenue account / Cr Net Assets (Unrestricted or Restricted).
     */
    private function generateRevenueClosingEntry(
        FinancialPeriod $period,
        User $user,
        array $revenueBalances,
        FinancialAccount $netAssetsUnrestricted,
        ?FinancialAccount $netAssetsRestricted
    ): void {
        if (empty($revenueBalances)) {
            return;
        }

        $totalUnrestricted = array_sum(array_column($revenueBalances, 'unrestricted_net'));
        $totalRestricted = array_sum(array_column($revenueBalances, 'restricted_net'));
        $totalNet = $totalUnrestricted + $totalRestricted;

        if ($totalNet === 0) {
            return;
        }

        $entry = FinancialJournalEntry::create([
            'period_id' => $period->id,
            'date' => $period->end_date->toDateString(),
            'description' => "Closing entry — Revenue accounts — {$period->name}",
            'entry_type' => 'closing',
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_id' => $user->id,
            'created_by_id' => $user->id,
        ]);

        // Debit each revenue account to zero it out
        foreach ($revenueBalances as $data) {
            $entry->lines()->create([
                'account_id' => $data['account']->id,
                'debit' => $data['net'],
                'credit' => 0,
                'memo' => 'Closing entry',
            ]);
        }

        // Credit Net Assets — Unrestricted for unrestricted portion
        if ($totalUnrestricted !== 0) {
            $entry->lines()->create([
                'account_id' => $netAssetsUnrestricted->id,
                'debit' => 0,
                'credit' => $totalUnrestricted,
                'memo' => 'Closing entry — unrestricted revenue',
            ]);
        }

        // Credit Net Assets — Restricted for restricted portion
        if ($totalRestricted !== 0 && $netAssetsRestricted) {
            $entry->lines()->create([
                'account_id' => $netAssetsRestricted->id,
                'debit' => 0,
                'credit' => $totalRestricted,
                'memo' => 'Closing entry — restricted revenue',
            ]);
        }
    }

    /**
     * Generate the expense closing entry.
     * Dr Net Assets — Unrestricted / Cr each expense account.
     */
    private function generateExpenseClosingEntry(
        FinancialPeriod $period,
        User $user,
        array $expenseBalances,
        FinancialAccount $netAssetsUnrestricted
    ): void {
        if (empty($expenseBalances)) {
            return;
        }

        // For expense accounts, net = credit - debit (negative means expenses consumed assets)
        // We want the absolute debit balance: debit - credit
        $totalExpense = 0;

        foreach ($expenseBalances as $data) {
            // Expense accounts have debit normal balance; net as stored is credit - debit (negative)
            // We need to pass a positive number: the absolute debit balance
            $totalExpense += abs($data['net']);
        }

        if ($totalExpense === 0) {
            return;
        }

        $entry = FinancialJournalEntry::create([
            'period_id' => $period->id,
            'date' => $period->end_date->toDateString(),
            'description' => "Closing entry — Expense accounts — {$period->name}",
            'entry_type' => 'closing',
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_id' => $user->id,
            'created_by_id' => $user->id,
        ]);

        // Debit Net Assets — Unrestricted by total expenses
        $entry->lines()->create([
            'account_id' => $netAssetsUnrestricted->id,
            'debit' => $totalExpense,
            'credit' => 0,
            'memo' => 'Closing entry — expenses',
        ]);

        // Credit each expense account to zero it out
        foreach ($expenseBalances as $data) {
            $expenseDebitBalance = abs($data['net']);

            if ($expenseDebitBalance > 0) {
                $entry->lines()->create([
                    'account_id' => $data['account']->id,
                    'debit' => 0,
                    'credit' => $expenseDebitBalance,
                    'memo' => 'Closing entry',
                ]);
            }
        }
    }
}
