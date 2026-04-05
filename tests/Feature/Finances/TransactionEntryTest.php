<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'transactions');

// == Transaction Creation == //

it('financials-treasurer can submit an income transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->income()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'income')
        ->set('accountId', (string) $account->id)
        ->set('amount', '50.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->call('submitTransaction');

    $this->assertDatabaseHas('financial_transactions', [
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'financial_category_id' => $category->id,
        'entered_by' => $user->id,
    ]);
});

it('financials-treasurer can submit an expense transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'expense')
        ->set('accountId', (string) $account->id)
        ->set('amount', '25.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->call('submitTransaction');

    $this->assertDatabaseHas('financial_transactions', [
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 2500,
    ]);
});

it('income transaction updates account balance', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->withOpeningBalance(10000)->create();
    $category = FinancialCategory::factory()->income()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'income')
        ->set('accountId', (string) $account->id)
        ->set('amount', '30.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->call('submitTransaction');

    expect($account->fresh()->currentBalance())->toBe(13000);
});

it('expense transaction decreases account balance', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->withOpeningBalance(10000)->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'expense')
        ->set('accountId', (string) $account->id)
        ->set('amount', '40.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->call('submitTransaction');

    expect($account->fresh()->currentBalance())->toBe(6000);
});

it('uses subcategory as category when both are selected', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $parent = FinancialCategory::factory()->expense()->create();
    $sub = FinancialCategory::factory()->subcategoryOf($parent)->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'expense')
        ->set('accountId', (string) $account->id)
        ->set('amount', '10.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $parent->id)
        ->set('subcategoryId', (string) $sub->id)
        ->call('submitTransaction');

    $this->assertDatabaseHas('financial_transactions', [
        'financial_category_id' => $sub->id,
    ]);
});

it('attaches tags to a transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $tag1 = FinancialTag::factory()->create(['created_by' => $user->id]);
    $tag2 = FinancialTag::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'expense')
        ->set('accountId', (string) $account->id)
        ->set('amount', '10.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->set('selectedTagIds', [$tag1->id, $tag2->id])
        ->call('submitTransaction');

    $transaction = FinancialTransaction::latest()->first();
    expect($transaction->tags->pluck('id')->toArray())
        ->toContain($tag1->id)
        ->toContain($tag2->id);
});

// == Authorization == //

it('financials-view user cannot submit a transaction', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'expense')
        ->set('accountId', (string) $account->id)
        ->set('amount', '10.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->call('submitTransaction')
        ->assertForbidden();

    expect(FinancialTransaction::count())->toBe(0);
});

it('unauthenticated user cannot access finance dashboard route', function () {
    $this->get(route('finances.dashboard'))
        ->assertRedirect(route('login'));
});

it('user without financials-view cannot access finance dashboard route', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('finances.dashboard'))
        ->assertForbidden();
});

// == Ready Room Finance Button == //

it('finance button appears in ready room for financials-view users', function () {
    $user = User::factory()->withRole('Financials - View')->withRole('Staff Access')->create();
    $this->actingAs($user);

    $this->get(route('ready-room.index'))
        ->assertSee('Finance')
        ->assertSee(route('finances.dashboard'));
});

it('finance button does not appear in ready room for users without financials-view', function () {
    $user = User::factory()->withRole('Staff Access')->create();
    $this->actingAs($user);

    $this->get(route('ready-room.index'))
        ->assertDontSee(route('finances.dashboard'));
});
