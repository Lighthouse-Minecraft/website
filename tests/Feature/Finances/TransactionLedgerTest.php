<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'ledger');

// == Ledger Display == //

it('ledger shows all transactions sorted newest first', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $old = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-01-01',
        'entered_by' => $user->id,
    ]);
    $new = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-01',
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.dashboard');
    $ledger = $component->instance()->ledger();

    expect($ledger->first()->id)->toBe($new->id)
        ->and($ledger->last()->id)->toBe($old->id);
});

// == Date Range Filtering == //

it('ledger filters by date from', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $included = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    $excluded = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-02-01',
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.dashboard')
        ->set('filterDateFrom', '2026-03-01');

    $ids = $component->instance()->ledger()->pluck('id');

    expect($ids)->toContain($included->id)
        ->not->toContain($excluded->id);
});

it('ledger filters by date to', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $included = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-01-15',
        'entered_by' => $user->id,
    ]);
    $excluded = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-01',
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.dashboard')
        ->set('filterDateTo', '2026-02-01');

    $ids = $component->instance()->ledger()->pluck('id');

    expect($ids)->toContain($included->id)
        ->not->toContain($excluded->id);
});

// == Account Filtering == //

it('ledger filters by account', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account1 = FinancialAccount::factory()->create();
    $account2 = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $tx1 = FinancialTransaction::factory()->create([
        'account_id' => $account1->id,
        'entered_by' => $user->id,
    ]);
    $tx2 = FinancialTransaction::factory()->create([
        'account_id' => $account2->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.dashboard')
        ->set('filterAccountId', (string) $account1->id);

    $ids = $component->instance()->ledger()->pluck('id');

    expect($ids)->toContain($tx1->id)
        ->not->toContain($tx2->id);
});

// == Category Filtering == //

it('ledger filters by top-level category and includes its subcategories', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $parent = FinancialCategory::factory()->expense()->create();
    $sub = FinancialCategory::factory()->subcategoryOf($parent)->create();
    $other = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    $txParent = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $parent->id,
        'entered_by' => $user->id,
    ]);
    $txSub = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $sub->id,
        'entered_by' => $user->id,
    ]);
    $txOther = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $other->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.dashboard')
        ->set('filterCategoryId', (string) $parent->id);

    $ids = $component->instance()->ledger()->pluck('id');

    expect($ids)->toContain($txParent->id)
        ->toContain($txSub->id)
        ->not->toContain($txOther->id);
});

// == Tag Filtering == //

it('ledger filters by tag', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $tag = FinancialTag::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    $txTagged = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
    ]);
    $txTagged->tags()->attach($tag->id);

    $txUntagged = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.dashboard')
        ->set('filterTagId', (string) $tag->id);

    $ids = $component->instance()->ledger()->pluck('id');

    expect($ids)->toContain($txTagged->id)
        ->not->toContain($txUntagged->id);
});

// == Edit Transaction == //

it('treasurer can edit an unpublished-month transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 1000,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => $category->id,
        'entered_by' => $user->id,
    ]);

    livewire('finances.dashboard')
        ->set('editTxId', $tx->id)
        ->set('editType', 'expense')
        ->set('editAccountId', (string) $account->id)
        ->set('editAmount', '20.00')
        ->set('editDate', '2026-03-15')
        ->set('editCategoryId', (string) $category->id)
        ->set('editNotes', 'Updated note')
        ->set('editTagIds', [])
        ->call('updateTransaction');

    expect($tx->fresh()->amount)->toBe(2000)
        ->and($tx->fresh()->notes)->toBe('Updated note');
});

it('treasurer can delete an unpublished-month transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
        'transacted_at' => '2026-03-15',
    ]);

    livewire('finances.dashboard')
        ->call('deleteTransaction', $tx->id);

    expect(FinancialTransaction::find($tx->id))->toBeNull();
});

// == Published Month Immutability == //

it('treasurer cannot edit a transaction in a published month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'entered_by' => $user->id,
        'transacted_at' => '2026-02-15',
    ]);

    FinancialPeriodReport::factory()->published()->forMonth('2026-02-01')->create();

    livewire('finances.dashboard')
        ->set('editTxId', $tx->id)
        ->set('editType', 'expense')
        ->set('editAccountId', (string) $account->id)
        ->set('editAmount', '9999')
        ->set('editDate', '2026-02-15')
        ->set('editCategoryId', (string) $category->id)
        ->set('editNotes', '')
        ->set('editTagIds', [])
        ->call('updateTransaction');

    expect($tx->fresh()->amount)->not->toBe(9999);
});

it('treasurer cannot delete a transaction in a published month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
        'transacted_at' => '2026-02-15',
    ]);

    FinancialPeriodReport::factory()->published()->forMonth('2026-02-01')->create();

    livewire('finances.dashboard')
        ->call('deleteTransaction', $tx->id);

    expect(FinancialTransaction::find($tx->id))->not->toBeNull();
});

// == Authorization == //

it('financials-view user cannot edit a transaction', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'entered_by' => $user->id,
        'transacted_at' => '2026-03-15',
    ]);

    livewire('finances.dashboard')
        ->set('editTxId', $tx->id)
        ->set('editType', 'expense')
        ->set('editAccountId', (string) $account->id)
        ->set('editAmount', '9999')
        ->set('editDate', '2026-03-15')
        ->set('editCategoryId', (string) $category->id)
        ->set('editNotes', '')
        ->set('editTagIds', [])
        ->call('updateTransaction')
        ->assertForbidden();
});

it('financials-view user cannot delete a transaction', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
        'transacted_at' => '2026-03-15',
    ]);

    livewire('finances.dashboard')
        ->call('deleteTransaction', $tx->id)
        ->assertForbidden();

    expect(FinancialTransaction::find($tx->id))->not->toBeNull();
});

it('financials-view user cannot open edit modal', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'entered_by' => $user->id,
    ]);

    livewire('finances.dashboard')
        ->call('openEditModal', $tx->id)
        ->assertForbidden();
});

it('treasurer cannot move a transaction date into a published month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'entered_by' => $user->id,
        'transacted_at' => '2026-03-15',
        'amount' => 1000,
    ]);

    FinancialPeriodReport::factory()->published()->forMonth('2026-02-01')->create();

    livewire('finances.dashboard')
        ->set('editTxId', $tx->id)
        ->set('editType', 'expense')
        ->set('editAccountId', (string) $account->id)
        ->set('editAmount', '1000')
        ->set('editDate', '2026-02-15')
        ->set('editCategoryId', (string) $category->id)
        ->set('editNotes', '')
        ->set('editTagIds', [])
        ->call('updateTransaction');

    expect($tx->fresh()->transacted_at->format('Y-m-d'))->toBe('2026-03-15');
});
