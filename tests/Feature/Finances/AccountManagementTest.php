<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'accounts');

// == Balance Calculation == //

it('calculates balance as opening_balance when no transactions exist', function () {
    $account = FinancialAccount::factory()->withOpeningBalance(10000)->create();

    expect($account->currentBalance())->toBe(10000);
});

it('adds income transactions to balance', function () {
    $user = User::factory()->create();
    $account = FinancialAccount::factory()->withOpeningBalance(5000)->create();

    FinancialTransaction::factory()->income(3000)->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
    ]);

    expect($account->fresh()->currentBalance())->toBe(8000);
});

it('subtracts expense transactions from balance', function () {
    $user = User::factory()->create();
    $account = FinancialAccount::factory()->withOpeningBalance(10000)->create();

    FinancialTransaction::factory()->expense(4000)->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
    ]);

    expect($account->fresh()->currentBalance())->toBe(6000);
});

it('debits the source account for a transfer', function () {
    $user = User::factory()->create();
    $source = FinancialAccount::factory()->withOpeningBalance(10000)->create();
    $destination = FinancialAccount::factory()->create();

    FinancialTransaction::factory()->transfer($destination, 3000)->create([
        'account_id' => $source->id,
        'entered_by' => $user->id,
    ]);

    expect($source->fresh()->currentBalance())->toBe(7000);
});

it('credits the destination account for a transfer', function () {
    $user = User::factory()->create();
    $source = FinancialAccount::factory()->withOpeningBalance(10000)->create();
    $destination = FinancialAccount::factory()->withOpeningBalance(2000)->create();

    FinancialTransaction::factory()->transfer($destination, 3000)->create([
        'account_id' => $source->id,
        'entered_by' => $user->id,
    ]);

    expect($destination->fresh()->currentBalance())->toBe(5000);
});

it('excludes transfer amounts from income/expense totals on destination', function () {
    $user = User::factory()->create();
    $source = FinancialAccount::factory()->withOpeningBalance(10000)->create();
    $destination = FinancialAccount::factory()->withOpeningBalance(0)->create();

    FinancialTransaction::factory()->transfer($destination, 5000)->create([
        'account_id' => $source->id,
        'entered_by' => $user->id,
    ]);
    FinancialTransaction::factory()->income(1000)->create([
        'account_id' => $destination->id,
        'entered_by' => $user->id,
    ]);

    // Destination balance = 0 (opening) + 5000 (transfer_in) + 1000 (income) = 6000
    expect($destination->fresh()->currentBalance())->toBe(6000);
});

// == Account Management Livewire == //

it('financials-manage user can create an account', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.accounts')
        ->set('newName', 'Test Checking')
        ->set('newType', 'checking')
        ->set('newOpeningBalance', 5000)
        ->call('createAccount');

    $this->assertDatabaseHas('financial_accounts', [
        'name' => 'Test Checking',
        'type' => 'checking',
        'opening_balance' => 5000,
        'is_archived' => false,
    ]);
});

it('financials-manage user can rename an account', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create(['name' => 'Old Name']);
    $this->actingAs($user);

    livewire('finances.accounts')
        ->call('openEditModal', $account->id)
        ->set('editName', 'New Name')
        ->call('updateAccount');

    expect($account->fresh()->name)->toBe('New Name');
});

it('financials-manage user can archive an account', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $account = FinancialAccount::factory()->create(['is_archived' => false]);
    $this->actingAs($user);

    livewire('finances.accounts')
        ->call('archiveAccount', $account->id);

    expect($account->fresh()->is_archived)->toBeTrue();
});

it('user without financials-manage cannot create an account', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.accounts')
        ->set('newName', 'Unauthorized Account')
        ->set('newType', 'checking')
        ->set('newOpeningBalance', 0)
        ->call('createAccount')
        ->assertForbidden();
});

it('user without financials-manage cannot rename an account', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create(['name' => 'Original Name']);
    $this->actingAs($user);

    livewire('finances.accounts')
        ->call('openEditModal', $account->id)
        ->assertForbidden();

    expect($account->fresh()->name)->toBe('Original Name');
});

it('user without financials-manage cannot archive an account', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create(['is_archived' => false]);
    $this->actingAs($user);

    livewire('finances.accounts')
        ->call('archiveAccount', $account->id)
        ->assertForbidden();

    expect($account->fresh()->is_archived)->toBeFalse();
});

it('unauthenticated user cannot access finance accounts route', function () {
    $this->get(route('finances.accounts'))
        ->assertRedirect(route('login'));
});

it('user without financials-view cannot access finance accounts route', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('finances.accounts'))
        ->assertForbidden();
});
