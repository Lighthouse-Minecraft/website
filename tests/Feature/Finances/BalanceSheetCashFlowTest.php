<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'balance-sheet-cash-flow');

// == Balance Sheet == //

it('balance sheet shows each account balance as of end date', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create(['opening_balance' => 1000]);
    $this->actingAs($user);

    $cat = FinancialCategory::factory()->create(['type' => 'income', 'parent_id' => null]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $cat->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('endMonth', '2026-03');

    $bs = $component->instance()->balanceSheet();

    $found = collect($bs['accounts'])->firstWhere('name', $account->name);
    expect($found)->not->toBeNull();
    expect($found['balance'])->toBe(6000); // 1000 opening + 5000 income
});

it('balance sheet sums net assets across all accounts', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account1 = FinancialAccount::factory()->create(['opening_balance' => 2000]);
    $account2 = FinancialAccount::factory()->create(['opening_balance' => 3000]);
    $this->actingAs($user);

    $component = livewire('finances.board-reports')
        ->set('endMonth', '2026-03');

    $bs = $component->instance()->balanceSheet();

    expect($bs['netAssets'])->toBeGreaterThanOrEqual(5000);
});

it('balance sheet accounts for transfers correctly', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $source = FinancialAccount::factory()->create(['opening_balance' => 10000]);
    $target = FinancialAccount::factory()->create(['opening_balance' => 0]);
    $this->actingAs($user);

    // Transfer 3000 from source to target
    FinancialTransaction::factory()->create([
        'account_id' => $source->id,
        'target_account_id' => $target->id,
        'type' => 'transfer',
        'amount' => 3000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => null,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('endMonth', '2026-03');

    $bs = $component->instance()->balanceSheet();

    $sourceRow = collect($bs['accounts'])->firstWhere('name', $source->name);
    $targetRow = collect($bs['accounts'])->firstWhere('name', $target->name);

    expect($sourceRow['balance'])->toBe(7000); // 10000 - 3000 out
    expect($targetRow['balance'])->toBe(3000); // 0 + 3000 in
    // Net assets unchanged (transfer is internal)
    expect($bs['netAssets'])->toBe(10000);
});

it('balance sheet only counts transactions up to end date', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create(['opening_balance' => 0]);
    $this->actingAs($user);

    $cat = FinancialCategory::factory()->create(['type' => 'income', 'parent_id' => null]);

    // In range
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $cat->id,
        'entered_by' => $user->id,
    ]);

    // After end date — should NOT be included
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 2000,
        'transacted_at' => '2026-04-05',
        'financial_category_id' => $cat->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('endMonth', '2026-03');

    $bs = $component->instance()->balanceSheet();

    $found = collect($bs['accounts'])->firstWhere('name', $account->name);
    expect($found['balance'])->toBe(5000);
});

// == Cash Flow Statement == //

it('cash flow operating section shows income and expense by category', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $donations = FinancialCategory::factory()->create(['name' => 'Donations', 'type' => 'income', 'parent_id' => null]);
    $infra = FinancialCategory::factory()->create(['name' => 'Infrastructure', 'type' => 'expense', 'parent_id' => null]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 8000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 3000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => $infra->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $cf = $component->instance()->cashFlow();

    expect($cf['operating']['totalIncome'])->toBe(8000);
    expect($cf['operating']['totalExpense'])->toBe(3000);
    expect($cf['operating']['net'])->toBe(5000);
    expect($cf['netChange'])->toBe(5000);
});

it('cash flow financing section lists inter-account transfers', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $source = FinancialAccount::factory()->create(['name' => 'Checking', 'opening_balance' => 10000]);
    $target = FinancialAccount::factory()->create(['name' => 'Savings', 'opening_balance' => 0]);
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $source->id,
        'target_account_id' => $target->id,
        'type' => 'transfer',
        'amount' => 4000,
        'transacted_at' => '2026-03-20',
        'financial_category_id' => null,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $cf = $component->instance()->cashFlow();

    expect($cf['financing'])->toHaveCount(1);
    expect($cf['financing'][0]['from'])->toBe('Checking');
    expect($cf['financing'][0]['to'])->toBe('Savings');
    expect($cf['financing'][0]['amount'])->toBe(4000);
});

it('transfers do not affect cash flow net change', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $source = FinancialAccount::factory()->create(['opening_balance' => 10000]);
    $target = FinancialAccount::factory()->create(['opening_balance' => 0]);
    $this->actingAs($user);

    $cat = FinancialCategory::factory()->create(['type' => 'income', 'parent_id' => null]);

    FinancialTransaction::factory()->create([
        'account_id' => $source->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $cat->id,
        'entered_by' => $user->id,
    ]);

    // Transfer — should not affect net change
    FinancialTransaction::factory()->create([
        'account_id' => $source->id,
        'target_account_id' => $target->id,
        'type' => 'transfer',
        'amount' => 2000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => null,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.board-reports')
        ->set('startMonth', '2026-03')
        ->set('endMonth', '2026-03');

    $cf = $component->instance()->cashFlow();

    // Net change = income only (5000), transfer doesn't add to it
    expect($cf['netChange'])->toBe(5000);
});

// == PDF downloads == //

it('financials-manage user can download balance sheet PDF', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    $response = $this->get(route('finances.board-reports.balance-sheet.pdf', ['end' => '2026-03']));
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('financials-treasurer cannot download balance sheet PDF', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    $this->get(route('finances.board-reports.balance-sheet.pdf', ['end' => '2026-03']))
        ->assertForbidden();
});

it('unauthenticated user cannot download balance sheet PDF', function () {
    $this->get(route('finances.board-reports.balance-sheet.pdf', ['end' => '2026-03']))
        ->assertRedirect(route('login'));
});

it('financials-manage user can download cash flow PDF', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    $response = $this->get(route('finances.board-reports.cash-flow.pdf', [
        'start' => '2026-01',
        'end' => '2026-03',
    ]));
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('financials-treasurer cannot download cash flow PDF', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    $this->get(route('finances.board-reports.cash-flow.pdf', [
        'start' => '2026-01',
        'end' => '2026-03',
    ]))->assertForbidden();
});

it('unauthenticated user cannot download cash flow PDF', function () {
    $this->get(route('finances.board-reports.cash-flow.pdf', [
        'start' => '2026-01',
        'end' => '2026-03',
    ]))->assertRedirect(route('login'));
});
