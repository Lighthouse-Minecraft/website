<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'income-statement');

// == Authorization == //

it('financials-manage user can access board reports page', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.board-reports')
        ->assertOk();
});

it('financials-treasurer cannot access board reports page', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    livewire('finances.board-reports')
        ->assertForbidden();
});

it('financials-view user cannot access board reports page', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.board-reports')
        ->assertForbidden();
});

it('unauthenticated user is redirected from board reports', function () {
    $this->get(route('finances.board-reports'))
        ->assertRedirect(route('login'));
});

// == Category totals == //

it('income statement sums income by top-level category', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $donations = FinancialCategory::factory()->create([
        'name' => 'Donations',
        'type' => 'income',
        'parent_id' => null,
    ]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $report = $component->instance()->incomeStatement();

    expect($report['totalIncome'])->toBe(5000);
    expect($report['income'])->toHaveCount(1);
    expect($report['income'][0]['name'])->toBe('Donations');
    expect($report['income'][0]['total'])->toBe(5000);
});

it('income statement sums expenses by top-level category', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $infra = FinancialCategory::factory()->create([
        'name' => 'Infrastructure',
        'type' => 'expense',
        'parent_id' => null,
    ]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 3000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $infra->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $report = $component->instance()->incomeStatement();

    expect($report['totalExpense'])->toBe(3000);
    expect($report['expense'])->toHaveCount(1);
    expect($report['expense'][0]['name'])->toBe('Infrastructure');
    expect($report['expense'][0]['total'])->toBe(3000);
});

it('income statement includes subcategory breakdown under parent', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $infra = FinancialCategory::factory()->create([
        'name' => 'Infrastructure',
        'type' => 'expense',
        'parent_id' => null,
    ]);
    $hosting = FinancialCategory::factory()->create([
        'name' => 'Minecraft Hosting',
        'type' => 'expense',
        'parent_id' => $infra->id,
    ]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 2000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $hosting->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $report = $component->instance()->incomeStatement();

    expect($report['totalExpense'])->toBe(2000);
    expect($report['expense'])->toHaveCount(1);
    expect($report['expense'][0]['total'])->toBe(2000);
    expect($report['expense'][0]['subcategories'])->toHaveCount(1);
    expect($report['expense'][0]['subcategories'][0]['name'])->toBe('Minecraft Hosting');
    expect($report['expense'][0]['subcategories'][0]['amount'])->toBe(2000);
});

it('income statement computes correct net income', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $donations = FinancialCategory::factory()->create(['name' => 'Donations', 'type' => 'income', 'parent_id' => null]);
    $infra = FinancialCategory::factory()->create(['name' => 'Infrastructure', 'type' => 'expense', 'parent_id' => null]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 10000,
        'transacted_at' => '2026-03-01',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 4000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => $infra->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $report = $component->instance()->incomeStatement();

    expect($report['totalIncome'])->toBe(10000);
    expect($report['totalExpense'])->toBe(4000);
    expect($report['netIncome'])->toBe(6000);
});

// == Transfer exclusion == //

it('transfers are excluded from income statement totals', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account1 = FinancialAccount::factory()->create();
    $account2 = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $donations = FinancialCategory::factory()->create(['name' => 'Donations', 'type' => 'income', 'parent_id' => null]);

    FinancialTransaction::factory()->create([
        'account_id' => $account1->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);

    // Transfer — should be excluded
    FinancialTransaction::factory()->create([
        'account_id' => $account1->id,
        'target_account_id' => $account2->id,
        'type' => 'transfer',
        'amount' => 3000,
        'transacted_at' => '2026-03-20',
        'financial_category_id' => null,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $report = $component->instance()->incomeStatement();

    expect($report['totalIncome'])->toBe(5000);
    expect($report['totalExpense'])->toBe(0);
    expect($report['netIncome'])->toBe(5000);
});

// == Date range filtering == //

it('income statement respects the date range', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $donations = FinancialCategory::factory()->create(['name' => 'Donations', 'type' => 'income', 'parent_id' => null]);

    // In range
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);

    // Out of range
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 2000,
        'transacted_at' => '2026-01-10',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $report = $component->instance()->incomeStatement();

    expect($report['totalIncome'])->toBe(5000);
});

it('income statement covers a multi-month range', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $donations = FinancialCategory::factory()->create(['name' => 'Donations', 'type' => 'income', 'parent_id' => null]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 3000,
        'transacted_at' => '2026-01-15',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 4000,
        'transacted_at' => '2026-04-15',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-01')
        ->set('endMonth', '2026-04');

    $report = $component->instance()->incomeStatement();

    expect($report['totalIncome'])->toBe(7000);
});

// == Trimester presets == //

it('trimester 1 sets start and end months correctly', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    $fyStartYear = now()->month >= 10 ? now()->year : now()->year - 1;
    $calYear = $fyStartYear + 1;

    livewire('finances.board-reports')
        ->call('applyTrimester', 1)
        ->assertSet('startMonth', $fyStartYear.'-10')
        ->assertSet('endMonth', $calYear.'-01');
});

it('trimester 2 sets start and end months correctly', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    $fyStartYear = now()->month >= 10 ? now()->year : now()->year - 1;
    $calYear = $fyStartYear + 1;

    livewire('finances.board-reports')
        ->call('applyTrimester', 2)
        ->assertSet('startMonth', $calYear.'-02')
        ->assertSet('endMonth', $calYear.'-05');
});

it('trimester 3 sets start and end months correctly', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    $fyStartYear = now()->month >= 10 ? now()->year : now()->year - 1;
    $calYear = $fyStartYear + 1;

    livewire('finances.board-reports')
        ->call('applyTrimester', 3)
        ->assertSet('startMonth', $calYear.'-06')
        ->assertSet('endMonth', $calYear.'-09');
});

// == PDF download route == //

it('financials-manage user can download income statement PDF', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    $response = $this->get(route('finances.board-reports.income-statement.pdf', [
        'start' => '2026-01',
        'end' => '2026-03',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('financials-treasurer cannot download income statement PDF', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    $this->get(route('finances.board-reports.income-statement.pdf', [
        'start' => '2026-01',
        'end' => '2026-03',
    ]))->assertForbidden();
});

it('unauthenticated user cannot download income statement PDF', function () {
    $this->get(route('finances.board-reports.income-statement.pdf', [
        'start' => '2026-01',
        'end' => '2026-03',
    ]))->assertRedirect(route('login'));
});
