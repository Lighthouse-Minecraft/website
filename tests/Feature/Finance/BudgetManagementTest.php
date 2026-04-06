<?php

declare(strict_types=1);

use App\Actions\CopyPriorYearBudgets;
use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialPeriod;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'budgets');

// == Budget CRUD ==

it('Finance - View user can access budgets page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.budgets.index'))
        ->assertOk();
});

it('non-finance user is forbidden from budgets page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.budgets.index'))
        ->assertForbidden();
});

it('Finance - Manage user can set a budget amount for an account and period', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $period = FinancialPeriod::factory()->create();
    $account = FinancialAccount::factory()->create(['type' => 'expense']);

    Volt::actingAs($user)
        ->test('finance.budgets')
        ->call('updateBudget', $account->id, $period->id, '500.00');

    $budget = FinancialBudget::where('account_id', $account->id)
        ->where('period_id', $period->id)
        ->first();

    expect($budget)->not->toBeNull();
    expect($budget->amount)->toBe(50000); // $500.00 = 50000 cents
});

it('Finance - Manage user can update an existing budget amount', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $period = FinancialPeriod::factory()->create();
    $account = FinancialAccount::factory()->create(['type' => 'revenue']);

    FinancialBudget::create([
        'account_id' => $account->id,
        'period_id' => $period->id,
        'amount' => 10000,
    ]);

    Volt::actingAs($user)
        ->test('finance.budgets')
        ->call('updateBudget', $account->id, $period->id, '200.00');

    expect(FinancialBudget::where('account_id', $account->id)->where('period_id', $period->id)->first()->amount)
        ->toBe(20000);
});

it('Finance - View user cannot update budget amounts', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $period = FinancialPeriod::factory()->create();
    $account = FinancialAccount::factory()->create(['type' => 'expense']);

    Volt::actingAs($user)
        ->test('finance.budgets')
        ->call('updateBudget', $account->id, $period->id, '100.00')
        ->assertForbidden();
});

it('budget amounts are stored as integer cents', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $period = FinancialPeriod::factory()->create();
    $account = FinancialAccount::factory()->create(['type' => 'expense']);

    Volt::actingAs($user)
        ->test('finance.budgets')
        ->call('updateBudget', $account->id, $period->id, '10');

    expect(FinancialBudget::first()->amount)->toBe(1000);
});

// == FY rollup ==

it('FY rollup shows total budgeted per account across all periods', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $period1 = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 1]);
    $period2 = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 2]);
    $account = FinancialAccount::factory()->create(['type' => 'expense']);

    FinancialBudget::create(['account_id' => $account->id, 'period_id' => $period1->id, 'amount' => 10000]);
    FinancialBudget::create(['account_id' => $account->id, 'period_id' => $period2->id, 'amount' => 20000]);

    $component = Volt::actingAs($user)->test('finance.budgets');
    $component->set('selectedYear', 2026);

    $budgetData = $component->get('budgetData');

    expect($budgetData[$account->id][$period1->id])->toBe(10000);
    expect($budgetData[$account->id][$period2->id])->toBe(20000);
});

// == Copy prior year ==

it('CopyPriorYearBudgets copies all budget entries from prior year', function () {
    $period2025 = FinancialPeriod::factory()->create(['fiscal_year' => 2025, 'month_number' => 1]);
    $period2026 = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 1]);
    $account = FinancialAccount::factory()->create(['type' => 'expense']);

    FinancialBudget::create(['account_id' => $account->id, 'period_id' => $period2025->id, 'amount' => 50000]);

    $copied = CopyPriorYearBudgets::run(2025, 2026);

    expect($copied)->toBe(1);

    $newBudget = FinancialBudget::where('account_id', $account->id)
        ->where('period_id', $period2026->id)
        ->first();

    expect($newBudget)->not->toBeNull();
    expect($newBudget->amount)->toBe(50000);
});

it('CopyPriorYearBudgets updates existing budget if one already exists for the target year', function () {
    $period2025 = FinancialPeriod::factory()->create(['fiscal_year' => 2025, 'month_number' => 3]);
    $period2026 = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 3]);
    $account = FinancialAccount::factory()->create(['type' => 'revenue']);

    FinancialBudget::create(['account_id' => $account->id, 'period_id' => $period2025->id, 'amount' => 75000]);
    FinancialBudget::create(['account_id' => $account->id, 'period_id' => $period2026->id, 'amount' => 10000]);

    CopyPriorYearBudgets::run(2025, 2026);

    expect(
        FinancialBudget::where('account_id', $account->id)->where('period_id', $period2026->id)->first()->amount
    )->toBe(75000);
});

it('CopyPriorYearBudgets returns 0 when prior year has no budgets', function () {
    FinancialPeriod::factory()->create(['fiscal_year' => 2024, 'month_number' => 1]);
    FinancialPeriod::factory()->create(['fiscal_year' => 2025, 'month_number' => 1]);

    $copied = CopyPriorYearBudgets::run(2024, 2025);

    expect($copied)->toBe(0);
});

it('Finance - Manage user can trigger copy prior year via component', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $account = FinancialAccount::factory()->create(['type' => 'expense']);

    // Use year 2024 → 2025 so the component's selectedYear can be set to 2025
    $period2024 = FinancialPeriod::factory()->create(['fiscal_year' => 2024, 'month_number' => 10]);
    $period2025 = FinancialPeriod::factory()->create(['fiscal_year' => 2025, 'month_number' => 10]);

    FinancialBudget::create(['account_id' => $account->id, 'period_id' => $period2024->id, 'amount' => 30000]);

    Volt::actingAs($user)
        ->test('finance.budgets')
        ->set('selectedYear', 2025)
        ->call('copyPriorYear');

    expect(
        FinancialBudget::where('account_id', $account->id)->where('period_id', $period2025->id)->exists()
    )->toBeTrue();
});

// == Variance report ==

it('variance report shows actual amounts from posted entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 1]);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense']);

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->format('Y-m-d'),
        description: 'Hosting bill',
        amountCents: 4000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.budgets');
    $component->set('selectedYear', 2026);

    $actualData = $component->get('actualData');

    expect($actualData[$expense->id][$period->id]['debit'])->toBe(4000);
});

it('variance report excludes draft entries from actuals', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 2]);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);

    // Create a draft entry — should NOT appear in actuals
    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->format('Y-m-d'),
        description: 'Draft donation',
        amountCents: 10000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.budgets');
    $component->set('selectedYear', 2026);

    $actualData = $component->get('actualData');

    // No actuals for this account in this period
    expect(isset($actualData[$revenue->id][$period->id]))->toBeFalse();
});
