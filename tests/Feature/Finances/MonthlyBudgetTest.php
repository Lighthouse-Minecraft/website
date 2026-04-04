<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\MonthlyBudget;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'budget');

// == Navigation == //

it('treasurer can access the budget page', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    $this->get(route('finances.budget'))
        ->assertOk();
});

it('financials-view user can access the budget page', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    $this->get(route('finances.budget'))
        ->assertOk();
});

it('budget page defaults to current month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    $component = livewire('finances.budget');
    expect($component->get('month'))->toBe(now()->format('Y-m'));
});

it('navigates to previous month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.budget')
        ->call('previousMonth')
        ->assertSet('month', now()->subMonth()->format('Y-m'));
});

it('navigates to next month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.budget')
        ->call('nextMonth')
        ->assertSet('month', now()->addMonth()->format('Y-m'));
});

// == Pre-fill Logic == //

it('pre-fills from previous month when no budget exists for current month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    // Create budget for previous month
    MonthlyBudget::factory()
        ->forCategory($category->id)
        ->forMonth(now()->subMonth()->startOfMonth()->toDateString())
        ->create(['planned_amount' => 5000]);

    $component = livewire('finances.budget');
    $amounts = $component->get('plannedAmounts');

    expect($amounts[$category->id])->toBe('5000');
});

it('starts blank when no prior month budget exists', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    $component = livewire('finances.budget');
    $amounts = $component->get('plannedAmounts');

    expect($amounts[$category->id])->toBe('');
});

it('loads existing budget for the selected month instead of pre-filling', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    // Budget for previous month (pre-fill source)
    MonthlyBudget::factory()
        ->forCategory($category->id)
        ->forMonth(now()->subMonth()->startOfMonth()->toDateString())
        ->create(['planned_amount' => 5000]);

    // Budget for current month (should take precedence)
    MonthlyBudget::factory()
        ->forCategory($category->id)
        ->forMonth(now()->startOfMonth()->toDateString())
        ->create(['planned_amount' => 7500]);

    $component = livewire('finances.budget');
    $amounts = $component->get('plannedAmounts');

    expect($amounts[$category->id])->toBe('7500');
});

// == Save Budget == //

it('treasurer can save planned amounts', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    livewire('finances.budget')
        ->set("plannedAmounts.{$category->id}", '10000')
        ->call('saveBudget');

    $this->assertDatabaseHas('monthly_budgets', [
        'financial_category_id' => $category->id,
        'planned_amount' => 10000,
    ]);
});

it('saving budget updates existing rows', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    MonthlyBudget::factory()
        ->forCategory($category->id)
        ->forMonth(now()->startOfMonth()->toDateString())
        ->create(['planned_amount' => 5000]);

    livewire('finances.budget')
        ->set("plannedAmounts.{$category->id}", '8000')
        ->call('saveBudget');

    expect(MonthlyBudget::where('financial_category_id', $category->id)->count())->toBe(1);
    expect(MonthlyBudget::where('financial_category_id', $category->id)->first()->planned_amount)->toBe(8000);
});

it('view-only user cannot save planned amounts', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    livewire('finances.budget')
        ->set("plannedAmounts.{$category->id}", '10000')
        ->call('saveBudget')
        ->assertForbidden();

    expect(MonthlyBudget::count())->toBe(0);
});

// == Variance Calculation == //

it('variance is planned minus actual', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    // Set planned amount
    MonthlyBudget::factory()
        ->forCategory($category->id)
        ->forMonth(now()->startOfMonth()->toDateString())
        ->create(['planned_amount' => 10000]);

    // Record a transaction in this month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 3000,
        'transacted_at' => now()->startOfMonth()->toDateString(),
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.budget');
    $rows = $component->instance()->budgetRows();
    $row = collect($rows)->firstWhere(fn ($r) => $r['category']->id === $category->id);

    expect($row['planned'])->toBe(10000)
        ->and($row['actual'])->toBe(3000)
        ->and($row['variance'])->toBe(7000);
});

it('actual includes subcategory transactions', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $parent = FinancialCategory::factory()->expense()->create();
    $sub = FinancialCategory::factory()->subcategoryOf($parent)->create();
    $this->actingAs($user);

    MonthlyBudget::factory()
        ->forCategory($parent->id)
        ->forMonth(now()->startOfMonth()->toDateString())
        ->create(['planned_amount' => 20000]);

    // Transaction against subcategory
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $sub->id,
        'type' => 'expense',
        'amount' => 4000,
        'transacted_at' => now()->startOfMonth()->toDateString(),
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.budget');
    $rows = $component->instance()->budgetRows();
    $row = collect($rows)->firstWhere(fn ($r) => $r['category']->id === $parent->id);

    expect($row['actual'])->toBe(4000);
});

it('only counts transactions in the selected month for actuals', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    // Transaction in this month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 3000,
        'transacted_at' => now()->startOfMonth()->toDateString(),
        'entered_by' => $user->id,
    ]);

    // Transaction in previous month (should not count)
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 9000,
        'transacted_at' => now()->subMonth()->startOfMonth()->toDateString(),
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.budget');
    $rows = $component->instance()->budgetRows();
    $row = collect($rows)->firstWhere(fn ($r) => $r['category']->id === $category->id);

    expect($row['actual'])->toBe(3000);
});
