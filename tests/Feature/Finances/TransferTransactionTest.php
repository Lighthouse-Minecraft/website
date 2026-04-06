<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'transfers');

it('treasurer can submit a transfer transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $from = FinancialAccount::factory()->create();
    $to = FinancialAccount::factory()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'transfer')
        ->set('accountId', (string) $from->id)
        ->set('targetAccountId', (string) $to->id)
        ->set('amount', '5000')
        ->set('transactedAt', '2026-04-01')
        ->call('submitTransaction');

    $this->assertDatabaseHas('financial_transactions', [
        'account_id' => $from->id,
        'target_account_id' => $to->id,
        'type' => 'transfer',
        'amount' => 5000,
    ]);
});

it('transfer decreases source account balance', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $from = FinancialAccount::factory()->withOpeningBalance(10000)->create();
    $to = FinancialAccount::factory()->withOpeningBalance(0)->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'transfer')
        ->set('accountId', (string) $from->id)
        ->set('targetAccountId', (string) $to->id)
        ->set('amount', '3000')
        ->set('transactedAt', '2026-04-01')
        ->call('submitTransaction');

    expect($from->fresh()->currentBalance())->toBe(7000);
});

it('transfer increases destination account balance', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $from = FinancialAccount::factory()->withOpeningBalance(10000)->create();
    $to = FinancialAccount::factory()->withOpeningBalance(0)->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'transfer')
        ->set('accountId', (string) $from->id)
        ->set('targetAccountId', (string) $to->id)
        ->set('amount', '3000')
        ->set('transactedAt', '2026-04-01')
        ->call('submitTransaction');

    expect($to->fresh()->currentBalance())->toBe(3000);
});

it('cannot transfer to the same account', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'transfer')
        ->set('accountId', (string) $account->id)
        ->set('targetAccountId', (string) $account->id)
        ->set('amount', '1000')
        ->set('transactedAt', '2026-04-01')
        ->call('submitTransaction')
        ->assertHasErrors(['targetAccountId']);

    expect(FinancialTransaction::count())->toBe(0);
});

it('transfer requires a target account', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'transfer')
        ->set('accountId', (string) $account->id)
        ->set('targetAccountId', '')
        ->set('amount', '1000')
        ->set('transactedAt', '2026-04-01')
        ->call('submitTransaction')
        ->assertHasErrors(['targetAccountId']);

    expect(FinancialTransaction::count())->toBe(0);
});

it('transfer does not appear in category totals', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $from = FinancialAccount::factory()->create();
    $to = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    // Record a transfer
    FinancialTransaction::factory()->create([
        'account_id' => $from->id,
        'target_account_id' => $to->id,
        'type' => 'transfer',
        'amount' => 5000,
        'transacted_at' => '2026-04-01',
        'financial_category_id' => null,
        'entered_by' => $user->id,
    ]);

    // Filter by category — transfer should not appear
    $component = livewire('finances.dashboard')
        ->set('filterCategoryId', (string) $category->id);

    $ids = $component->instance()->ledger()->pluck('id');

    expect($ids)->toBeEmpty();
});

it('transfer appears in ledger with both account names', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $from = FinancialAccount::factory()->create(['name' => 'Checking Account']);
    $to = FinancialAccount::factory()->create(['name' => 'Savings Account']);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'transfer')
        ->set('accountId', (string) $from->id)
        ->set('targetAccountId', (string) $to->id)
        ->set('amount', '1000')
        ->set('transactedAt', '2026-04-01')
        ->call('submitTransaction');

    $tx = FinancialTransaction::latest()->first();
    expect($tx->account->name)->toBe('Checking Account')
        ->and($tx->targetAccount->name)->toBe('Savings Account');
});
