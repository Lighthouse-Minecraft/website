<?php

declare(strict_types=1);

use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'finance-dashboard');

// == Access control ==

it('finance-view user can access finance dashboard', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.dashboard.index'))
        ->assertOk();
});

it('user without finance role cannot access finance dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.dashboard.index'))
        ->assertForbidden();
});

it('unauthenticated user cannot access finance dashboard', function () {
    $this->get(route('finance.dashboard.index'))
        ->assertRedirect(route('login'));
});

// == Cash position ==

it('cash position shows correct balance for a posted journal entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create([
        'name' => 'Checking Account',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_bank_account' => true,
        'is_active' => true,
    ]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Donation',
        amountCents: 50000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $component = Volt::actingAs($user)->test('finance.dashboard');
    $cashPosition = $component->get('cashPosition');

    $accountBalance = collect($cashPosition['accounts'])->firstWhere('name', 'Checking Account');
    expect($accountBalance['balance'])->toBe(50000);
});

it('draft entries are excluded from cash position', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create([
        'name' => 'Draft Test Bank',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_bank_account' => true,
        'is_active' => true,
    ]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Draft income',
        amountCents: 99900,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $component = Volt::actingAs($user)->test('finance.dashboard');
    $cashPosition = $component->get('cashPosition');

    $accountBalance = collect($cashPosition['accounts'])->firstWhere('name', 'Draft Test Bank');
    expect($accountBalance['balance'])->toBe(0);
});

// == Current month income/expense ==

it('current month income total reflects posted entries in current calendar month', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $period = FinancialPeriod::factory()->create([
        'status' => 'open',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);
    $bank = FinancialAccount::factory()->create([
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_bank_account' => true,
        'is_active' => true,
    ]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: now()->toDateString(),
        description: 'Monthly income',
        amountCents: 75000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $component = Volt::actingAs($user)->test('finance.dashboard');
    $currentMonth = $component->get('currentMonth');

    expect($currentMonth['income'])->toBe(75000);
});

it('current month expenses reflect posted entries in current calendar month', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $period = FinancialPeriod::factory()->create([
        'status' => 'open',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);
    $bank = FinancialAccount::factory()->create([
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_bank_account' => true,
        'is_active' => true,
    ]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: now()->toDateString(),
        description: 'Monthly expense',
        amountCents: 30000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $component = Volt::actingAs($user)->test('finance.dashboard');
    $currentMonth = $component->get('currentMonth');

    expect($currentMonth['expenses'])->toBe(30000);
});

it('draft entries are excluded from current month totals', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $period = FinancialPeriod::factory()->create([
        'status' => 'open',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);
    $bank = FinancialAccount::factory()->create([
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_bank_account' => true,
        'is_active' => true,
    ]);
    $revenue = FinancialAccount::factory()->create([
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'name' => 'Draft Revenue Account',
        'code' => 88001,
    ]);

    // Only post a draft — income should remain 0 (from this account)
    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: now()->toDateString(),
        description: 'Should not appear',
        amountCents: 123456,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $component = Volt::actingAs($user)->test('finance.dashboard');
    $currentMonth = $component->get('currentMonth');

    expect($currentMonth['income'])->toBe(0);
});

// == Pending drafts count ==

it('pending drafts count matches draft journal entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $initialCount = FinancialJournalEntry::where('status', 'draft')->count();

    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create([
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_bank_account' => true,
        'is_active' => true,
    ]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Draft entry',
        amountCents: 10000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $component = Volt::actingAs($user)->test('finance.dashboard');

    expect($component->get('pendingDrafts'))->toBe($initialCount + 1);
});
