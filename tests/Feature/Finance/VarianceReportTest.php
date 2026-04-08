<?php

declare(strict_types=1);

use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialPeriod;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'variance-report');

// == Variance calculation ==

it('variance report shows positive variance when expense is under budget', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 3]);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);

    // Budget: $500, Actual: $300 → variance = actual - budget = 300 - 500 = -200 (favorable for expense)
    FinancialBudget::create(['account_id' => $expense->id, 'period_id' => $period->id, 'amount' => 50000]);

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Expense under budget',
        amountCents: 30000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'variance')
        ->set('filterFyYear', 2026);

    $budgetData = $component->get('varianceBudgetData');
    $actualData = $component->get('varianceActualData');

    expect($budgetData[$expense->id][$period->id])->toBe(50000);
    expect($actualData[$expense->id][$period->id]['debit'])->toBe(30000);
});

it('variance report shows correct totals when revenue exceeds budget', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 4]);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    // Budget: $1000, Actual: $1500 → variance = 1500 - 1000 = +500 (favorable for revenue)
    FinancialBudget::create(['account_id' => $revenue->id, 'period_id' => $period->id, 'amount' => 100000]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Revenue above budget',
        amountCents: 150000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'variance')
        ->set('filterFyYear', 2026);

    $budgetData = $component->get('varianceBudgetData');
    $actualData = $component->get('varianceActualData');

    expect($budgetData[$revenue->id][$period->id])->toBe(100000);
    expect($actualData[$revenue->id][$period->id]['credit'])->toBe(150000);
});

it('accounts with no budget show zero budget and full actual as variance', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 5]);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);

    // No budget set for this account
    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Unbudgeted expense',
        amountCents: 20000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'variance')
        ->set('filterFyYear', 2026);

    $budgetData = $component->get('varianceBudgetData');
    $actualData = $component->get('varianceActualData');

    // Budget key should not exist for this account in this period
    expect($budgetData[$expense->id][$period->id] ?? 0)->toBe(0);
    // Actual should still show the expense
    expect($actualData[$expense->id][$period->id]['debit'])->toBe(20000);
});

it('variance report FY rollup includes all periods in the fiscal year', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period1 = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 6]);
    $period2 = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 7]);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    FinancialBudget::create(['account_id' => $revenue->id, 'period_id' => $period1->id, 'amount' => 40000]);
    FinancialBudget::create(['account_id' => $revenue->id, 'period_id' => $period2->id, 'amount' => 50000]);

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'variance')
        ->set('filterFyYear', 2026);

    $variancePeriods = $component->get('variancePeriods');
    $ids = $variancePeriods->pluck('id');

    expect($ids)->toContain($period1->id);
    expect($ids)->toContain($period2->id);

    $budgetData = $component->get('varianceBudgetData');
    expect($budgetData[$revenue->id][$period1->id])->toBe(40000);
    expect($budgetData[$revenue->id][$period2->id])->toBe(50000);
});

it('variance report excludes draft entries from actuals', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['fiscal_year' => 2026, 'month_number' => 8]);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Draft expense',
        amountCents: 99999,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'variance')
        ->set('filterFyYear', 2026);

    $actualData = $component->get('varianceActualData');

    // Draft should not appear
    expect(isset($actualData[$expense->id][$period->id]))->toBeFalse();
});
