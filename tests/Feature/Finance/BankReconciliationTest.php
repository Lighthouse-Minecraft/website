<?php

declare(strict_types=1);

use App\Actions\CompleteReconciliation;
use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntryLine;
use App\Models\FinancialPeriod;
use App\Models\FinancialReconciliation;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'bank-reconciliation');

// == Page access ==

it('Finance - Record user can access the reconciliation page', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $account = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $period = FinancialPeriod::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.reconciliation.show', ['accountId' => $account->id, 'periodId' => $period->id]))
        ->assertOk();
});

it('Finance - View user cannot access the reconciliation page', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $account = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $period = FinancialPeriod::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.reconciliation.show', ['accountId' => $account->id, 'periodId' => $period->id]))
        ->assertForbidden();
});

it('non-bank account is rejected', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $account = FinancialAccount::factory()->create(['type' => 'expense', 'is_bank_account' => false]);
    $period = FinancialPeriod::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.reconciliation.show', ['accountId' => $account->id, 'periodId' => $period->id]))
        ->assertForbidden();
});

// == CompleteReconciliation action ==

it('CompleteReconciliation marks reconciliation as completed when difference is zero', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Test deposit',
        amountCents: 10000,
        primaryAccountId: FinancialAccount::factory()->create(['type' => 'revenue'])->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $line = FinancialJournalEntryLine::where('account_id', $bank->id)->first();

    $rec = FinancialReconciliation::create([
        'account_id' => $bank->id,
        'period_id' => $period->id,
        'statement_date' => '2026-01-31',
        'statement_ending_balance' => 10000,
        'status' => 'in_progress',
    ]);

    $rec->lines()->create([
        'journal_entry_line_id' => $line->id,
        'cleared_at' => now(),
    ]);

    CompleteReconciliation::run($rec, $user);

    expect($rec->fresh()->status)->toBe('completed');
    expect($rec->fresh()->completed_at)->not->toBeNull();
    expect($rec->fresh()->completed_by_id)->toBe($user->id);
});

it('CompleteReconciliation throws when difference is not zero', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    $rec = FinancialReconciliation::create([
        'account_id' => $bank->id,
        'period_id' => $period->id,
        'statement_date' => '2026-01-31',
        'statement_ending_balance' => 10000,
        'status' => 'in_progress',
    ]);

    // No cleared lines — cleared balance is 0, difference is 10000

    expect(fn () => CompleteReconciliation::run($rec, $user))
        ->toThrow(\RuntimeException::class);
});

it('CompleteReconciliation throws when already completed', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    $rec = FinancialReconciliation::create([
        'account_id' => $bank->id,
        'period_id' => $period->id,
        'statement_date' => '2026-01-31',
        'statement_ending_balance' => 0,
        'status' => 'completed',
        'completed_at' => now(),
        'completed_by_id' => $user->id,
    ]);

    expect(fn () => CompleteReconciliation::run($rec, $user))
        ->toThrow(\RuntimeException::class, 'already completed');
});

// == Mark / unmark cleared ==

it('user can mark a line as cleared', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Test income',
        amountCents: 5000,
        primaryAccountId: FinancialAccount::factory()->create(['type' => 'revenue'])->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $line = FinancialJournalEntryLine::where('account_id', $bank->id)->first();

    $rec = FinancialReconciliation::create([
        'account_id' => $bank->id,
        'period_id' => $period->id,
        'statement_date' => '2026-01-31',
        'statement_ending_balance' => 5000,
        'status' => 'in_progress',
    ]);

    Volt::actingAs($user)
        ->test('finance.bank-reconciliation', ['accountId' => $bank->id, 'periodId' => $period->id])
        ->call('markCleared', $line->id);

    expect($rec->lines()->where('journal_entry_line_id', $line->id)->exists())->toBeTrue();
});

it('user can unmark a cleared line', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Test income',
        amountCents: 5000,
        primaryAccountId: FinancialAccount::factory()->create(['type' => 'revenue'])->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $line = FinancialJournalEntryLine::where('account_id', $bank->id)->first();

    $rec = FinancialReconciliation::create([
        'account_id' => $bank->id,
        'period_id' => $period->id,
        'statement_date' => '2026-01-31',
        'statement_ending_balance' => 5000,
        'status' => 'in_progress',
    ]);

    $recLine = $rec->lines()->create([
        'journal_entry_line_id' => $line->id,
        'cleared_at' => now(),
    ]);

    Volt::actingAs($user)
        ->test('finance.bank-reconciliation', ['accountId' => $bank->id, 'periodId' => $period->id])
        ->call('unmarkCleared', $recLine->id);

    expect($rec->lines()->where('id', $recLine->id)->exists())->toBeFalse();
});

// == Statement balance + complete button ==

it('complete button is disabled when difference is not zero', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    $component = Volt::actingAs($user)
        ->test('finance.bank-reconciliation', ['accountId' => $bank->id, 'periodId' => $period->id]);

    // No lines cleared, statement balance still 0 → difference is 0 but statementBalanceCents is 0
    // Set a non-zero statement balance to create a difference
    $component->set('statementBalance', '100.00');

    expect($component->get('isBalanced'))->toBeFalse();
});

it('complete button is enabled when difference is zero and statement balance is set', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Test income',
        amountCents: 7500,
        primaryAccountId: FinancialAccount::factory()->create(['type' => 'revenue'])->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $line = FinancialJournalEntryLine::where('account_id', $bank->id)->first();

    $component = Volt::actingAs($user)
        ->test('finance.bank-reconciliation', ['accountId' => $bank->id, 'periodId' => $period->id]);

    // Mark the line cleared
    $component->call('markCleared', $line->id);

    // Set statement balance to match
    $component->set('statementBalance', '75.00');
    $component->call('updateStatementBalance');

    expect($component->get('isBalanced'))->toBeTrue();
});

it('reconciliation can be completed through the Livewire component', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Test income',
        amountCents: 5000,
        primaryAccountId: FinancialAccount::factory()->create(['type' => 'revenue'])->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $line = FinancialJournalEntryLine::where('account_id', $bank->id)->first();

    $component = Volt::actingAs($user)
        ->test('finance.bank-reconciliation', ['accountId' => $bank->id, 'periodId' => $period->id]);

    $component->call('markCleared', $line->id);
    $component->set('statementBalance', '50.00');
    $component->call('complete');

    $rec = FinancialReconciliation::where('account_id', $bank->id)->where('period_id', $period->id)->first();
    expect($rec->status)->toBe('completed');
});

// == Reconciliation status on fiscal periods page ==

it('reconciliation status appears on fiscal periods page for bank accounts', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $bank = FinancialAccount::factory()->create([
        'type' => 'asset',
        'is_bank_account' => true,
        'is_active' => true,
        'name' => 'Checking Account',
    ]);

    $component = Volt::actingAs($user)->test('finance.fiscal-periods');

    // Should not throw — bank accounts computed property should load
    expect($component->get('bankAccounts')->pluck('name'))->toContain('Checking Account');
});
