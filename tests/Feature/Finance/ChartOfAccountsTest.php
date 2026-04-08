<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'chart-of-accounts');

// == Seeded data == //

it('seeds the standard chart of accounts with 16 accounts', function () {
    expect(FinancialAccount::count())->toBe(16);
});

it('seeds accounts covering asset, net_assets, revenue, and expense types', function () {
    expect(FinancialAccount::where('type', 'asset')->count())->toBeGreaterThanOrEqual(1);
    expect(FinancialAccount::where('type', 'net_assets')->count())->toBeGreaterThanOrEqual(1);
    expect(FinancialAccount::where('type', 'revenue')->count())->toBeGreaterThanOrEqual(1);
    expect(FinancialAccount::where('type', 'expense')->count())->toBeGreaterThanOrEqual(1);
});

it('seeds bank accounts for the cash accounts', function () {
    expect(FinancialAccount::where('is_bank_account', true)->count())->toBe(3);

    $bankCodes = FinancialAccount::where('is_bank_account', true)->pluck('code')->sort()->values()->toArray();
    expect($bankCodes)->toBe([1010, 1020, 1030]);
});

// == View (Finance - View can see accounts) == //

it('Finance - View user can view the chart of accounts page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.accounts.index'))
        ->assertOk();
});

it('shows all account types grouped on the chart of accounts page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->assertSee('Assets')
        ->assertSee('Net Assets')
        ->assertSee('Revenue')
        ->assertSee('Expenses');
});

// == Add account (Finance - Manage only) == //

it('Finance - Manage user can add a new account', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->set('newCode', '6000')
        ->set('newName', 'Equipment')
        ->set('newType', 'asset')
        ->set('newNormalBalance', 'debit')
        ->set('newFundType', 'unrestricted')
        ->call('addAccount');

    expect(FinancialAccount::where('code', 6000)->exists())->toBeTrue();
});

it('Finance - View user cannot add an account', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->set('newCode', '6001')
        ->set('newName', 'Test')
        ->set('newType', 'asset')
        ->set('newNormalBalance', 'debit')
        ->set('newFundType', 'unrestricted')
        ->call('addAccount')
        ->assertForbidden();
});

it('validates required fields when adding an account', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->call('addAccount')
        ->assertHasErrors(['newCode', 'newName', 'newType', 'newNormalBalance']);
});

// == Deactivate account == //

it('Finance - Manage user can deactivate an account', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $account = FinancialAccount::where('code', 1000)->first();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->call('deactivate', $account->id);

    expect($account->fresh()->is_active)->toBeFalse();
});

it('Finance - View user cannot deactivate an account', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $account = FinancialAccount::where('code', 1000)->first();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->call('deactivate', $account->id)
        ->assertForbidden();
});

it('deactivated account is preserved in the database', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $account = FinancialAccount::where('code', 1000)->first();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->call('deactivate', $account->id);

    // Record still exists
    expect(FinancialAccount::where('code', 1000)->exists())->toBeTrue();
    expect($account->fresh()->is_active)->toBeFalse();
});

// == Reactivate account == //

it('Finance - Manage user can reactivate a deactivated account', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $account = FinancialAccount::factory()->inactive()->create();

    Volt::actingAs($user)
        ->test('finance.chart-of-accounts')
        ->call('reactivate', $account->id);

    expect($account->fresh()->is_active)->toBeTrue();
});
