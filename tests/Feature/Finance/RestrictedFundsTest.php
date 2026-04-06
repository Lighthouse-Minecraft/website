<?php

declare(strict_types=1);

use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use App\Models\FinancialRestrictedFund;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'restricted-funds');

// == Fund management page ==

it('Finance - View user can access restricted funds page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.restricted-funds.index'))
        ->assertOk();
});

it('non-finance user is forbidden from restricted funds page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.restricted-funds.index'))
        ->assertForbidden();
});

// == Fund CRUD ==

it('Finance - Manage user can create a restricted fund', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.restricted-funds')
        ->set('newName', 'Server Fund Drive 2025')
        ->set('newDescription', 'Funds for upgrading server infrastructure')
        ->call('createFund');

    expect(FinancialRestrictedFund::where('name', 'Server Fund Drive 2025')->exists())->toBeTrue();
    expect(FinancialRestrictedFund::where('name', 'Server Fund Drive 2025')->first()->is_active)->toBeTrue();
});

it('Finance - View user cannot create a restricted fund', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    Volt::actingAs($user)
        ->test('finance.restricted-funds')
        ->set('newName', 'Test Fund')
        ->call('createFund')
        ->assertForbidden();
});

it('validates fund name is required', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.restricted-funds')
        ->call('createFund')
        ->assertHasErrors(['newName']);
});

it('Finance - Manage user can update a restricted fund', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $fund = FinancialRestrictedFund::factory()->create(['name' => 'Old Name']);

    Volt::actingAs($user)
        ->test('finance.restricted-funds')
        ->call('startEdit', $fund->id)
        ->set('editName', 'New Name')
        ->call('updateFund');

    expect($fund->fresh()->name)->toBe('New Name');
});

it('Finance - Manage user can deactivate a restricted fund', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $fund = FinancialRestrictedFund::factory()->create();

    Volt::actingAs($user)
        ->test('finance.restricted-funds')
        ->call('deactivate', $fund->id);

    expect($fund->fresh()->is_active)->toBeFalse();
});

it('Finance - Manage user can reactivate a restricted fund', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $fund = FinancialRestrictedFund::factory()->inactive()->create();

    Volt::actingAs($user)
        ->test('finance.restricted-funds')
        ->call('reactivate', $fund->id);

    expect($fund->fresh()->is_active)->toBeTrue();
});

it('deactivated fund does not appear in fund summaries by default', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $activeFund = FinancialRestrictedFund::factory()->create(['name' => 'Active Fund']);
    $inactiveFund = FinancialRestrictedFund::factory()->inactive()->create(['name' => 'Inactive Fund']);

    $component = Volt::actingAs($user)->test('finance.restricted-funds');

    $names = $component->get('funds')->pluck('name')->toArray();

    expect($names)->toContain('Active Fund');
    expect($names)->not->toContain('Inactive Fund');
});

// == Balance calculation ==

it('fund summary correctly calculates received from posted income entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);
    $fund = FinancialRestrictedFund::factory()->create();

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Restricted donation',
        amountCents: 10000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
        restrictedFundId: $fund->id,
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.restricted-funds');
    $summaries = $component->get('fundSummaries');

    expect($summaries[$fund->id]['received'])->toBe(10000);
    expect($summaries[$fund->id]['spent'])->toBe(0);
    expect($summaries[$fund->id]['remaining'])->toBe(10000);
});

it('fund summary correctly calculates spent from posted expense entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense']);
    $fund = FinancialRestrictedFund::factory()->create();

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Restricted spend',
        amountCents: 3000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
        restrictedFundId: $fund->id,
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.restricted-funds');
    $summaries = $component->get('fundSummaries');

    expect($summaries[$fund->id]['spent'])->toBe(3000);
    expect($summaries[$fund->id]['remaining'])->toBe(-3000);
});

it('fund summary excludes draft entries from balance calculation', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);
    $fund = FinancialRestrictedFund::factory()->create();

    // Draft entry — should NOT count in balance
    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Draft restricted donation',
        amountCents: 5000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
        restrictedFundId: $fund->id,
    );

    $viewer = User::factory()->withRole('Finance - View')->create();
    $component = Volt::actingAs($viewer)->test('finance.restricted-funds');
    $summaries = $component->get('fundSummaries');

    expect($summaries[$fund->id]['received'])->toBe(0);
});

// == Restricted fund selector in create form ==

it('restricted fund is stored on income journal entry when selected', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);
    $fund = FinancialRestrictedFund::factory()->create();

    $period->update([
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    Volt::actingAs($user)
        ->test('finance.create-journal-entry')
        ->set('entryType', 'income')
        ->set('date', now()->format('Y-m-d'))
        ->set('description', 'Restricted income')
        ->set('amount', '50.00')
        ->set('revenueAccountId', $revenue->id)
        ->set('bankAccountId', $bank->id)
        ->set('restrictedFundId', $fund->id)
        ->call('save', 'posted');

    $entry = \App\Models\FinancialJournalEntry::where('description', 'Restricted income')->first();
    expect($entry->restricted_fund_id)->toBe($fund->id);
});
