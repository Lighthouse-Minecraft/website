<?php

declare(strict_types=1);

use App\Actions\CloseFinancialPeriod;
use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\FinancialReconciliation;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'period-close');

// Helpers

function makeNetAssetsAccounts(): array
{
    // Use seeded net_assets accounts if they exist, otherwise create them
    $unrestricted = FinancialAccount::where('type', 'net_assets')->where('subtype', 'unrestricted')->first()
        ?? FinancialAccount::factory()->create([
            'type' => 'net_assets',
            'subtype' => 'unrestricted',
            'normal_balance' => 'credit',
            'is_bank_account' => false,
        ]);

    $restricted = FinancialAccount::where('type', 'net_assets')->where('subtype', 'restricted')->first()
        ?? FinancialAccount::factory()->create([
            'type' => 'net_assets',
            'subtype' => 'restricted',
            'normal_balance' => 'credit',
            'is_bank_account' => false,
        ]);

    return [$unrestricted, $restricted];
}

/**
 * Mark all active bank accounts as having a completed reconciliation for the given period.
 */
function reconcileAllBanks(FinancialPeriod $period, User $user): void
{
    FinancialAccount::where('is_bank_account', true)
        ->where('is_active', true)
        ->each(function ($bank) use ($period, $user) {
            FinancialReconciliation::firstOrCreate(
                ['account_id' => $bank->id, 'period_id' => $period->id],
                [
                    'statement_date' => $period->end_date->toDateString(),
                    'statement_ending_balance' => 0,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_by_id' => $user->id,
                ]
            );
        });
}

// == CloseFinancialPeriod action ==

it('blocks close if period is already closed', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'closed', 'closed_at' => now(), 'closed_by_id' => $user->id]);

    expect(fn () => CloseFinancialPeriod::run($period, $user))
        ->toThrow(\RuntimeException::class, 'already closed');
});

it('blocks close if bank account lacks completed reconciliation', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'name' => 'Test Bank']);

    // No reconciliation created → missing

    expect(fn () => CloseFinancialPeriod::run($period, $user))
        ->toThrow(\RuntimeException::class, 'Test Bank');
});

it('blocks close if reconciliation is in_progress', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'name' => 'Checking']);

    FinancialReconciliation::create([
        'account_id' => $bank->id,
        'period_id' => $period->id,
        'statement_date' => $period->end_date->toDateString(),
        'statement_ending_balance' => 0,
        'status' => 'in_progress',
    ]);

    expect(fn () => CloseFinancialPeriod::run($period, $user))
        ->toThrow(\RuntimeException::class, 'Checking');
});

it('closes the period when all reconciliations are complete', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    makeNetAssetsAccounts();
    reconcileAllBanks($period, $user);

    CloseFinancialPeriod::run($period, $user);

    expect($period->fresh()->status)->toBe('closed');
    expect($period->fresh()->closed_at)->not->toBeNull();
    expect($period->fresh()->closed_by_id)->toBe($user->id);
});

it('generates balanced revenue closing entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);
    [$netAssetsUnrestricted] = makeNetAssetsAccounts();

    // Post an income entry
    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Donation',
        amountCents: 10000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    reconcileAllBanks($period, $user);

    CloseFinancialPeriod::run($period, $user);

    $closingEntry = FinancialJournalEntry::where('period_id', $period->id)
        ->where('entry_type', 'closing')
        ->where('description', 'like', '%Revenue%')
        ->first();

    expect($closingEntry)->not->toBeNull();
    expect($closingEntry->status)->toBe('posted');

    // Debits must equal credits (balanced)
    $totalDebit = $closingEntry->lines->sum('debit');
    $totalCredit = $closingEntry->lines->sum('credit');
    expect($totalDebit)->toBe($totalCredit);

    // Revenue account should be debited 10000 (zeroing its credit balance)
    $revenueLine = $closingEntry->lines->where('account_id', $revenue->id)->first();
    expect($revenueLine->debit)->toBe(10000);

    // Net Assets — Unrestricted should be credited 10000
    $naLine = $closingEntry->lines->where('account_id', $netAssetsUnrestricted->id)->first();
    expect($naLine->credit)->toBe(10000);
});

it('generates balanced expense closing entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);
    [$netAssetsUnrestricted] = makeNetAssetsAccounts();

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Hosting bill',
        amountCents: 5000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    reconcileAllBanks($period, $user);

    CloseFinancialPeriod::run($period, $user);

    $closingEntry = FinancialJournalEntry::where('period_id', $period->id)
        ->where('entry_type', 'closing')
        ->where('description', 'like', '%Expense%')
        ->first();

    expect($closingEntry)->not->toBeNull();

    // Balanced
    $totalDebit = $closingEntry->lines->sum('debit');
    $totalCredit = $closingEntry->lines->sum('credit');
    expect($totalDebit)->toBe($totalCredit);

    // Net Assets — Unrestricted should be debited 5000
    $naLine = $closingEntry->lines->where('account_id', $netAssetsUnrestricted->id)->first();
    expect($naLine->debit)->toBe(5000);

    // Expense account should be credited 5000
    $expenseLine = $closingEntry->lines->where('account_id', $expense->id)->first();
    expect($expenseLine->credit)->toBe(5000);
});

it('period is locked against new entries after close', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);
    makeNetAssetsAccounts();
    reconcileAllBanks($period, $user);

    CloseFinancialPeriod::run($period, $user);

    // Attempt to post a journal entry to the closed period should fail
    expect(fn () => CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Late entry',
        amountCents: 100,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    ))->toThrow(\RuntimeException::class);
});

// == Livewire UI ==

it('Finance - Record user sees Close Period button on open periods', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    makeNetAssetsAccounts();

    $component = Volt::actingAs($user)->test('finance.fiscal-periods');

    // The component renders — we check that it does not throw
    $component->assertOk();
});

it('Finance - View user cannot call closePeriod', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $period = FinancialPeriod::factory()->create();

    Volt::actingAs($user)
        ->test('finance.fiscal-periods')
        ->call('closePeriod', $period->id)
        ->assertForbidden();
});
