<?php

declare(strict_types=1);

use App\Actions\PublishPeriodReport;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'report-detail');

// == Route access == //

it('treasurer can access the detail page for an unpublished month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    $this->get(route('finances.reports.show', ['month' => '2026-03']))->assertOk();
});

it('treasurer can access the detail page for a published month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $this->get(route('finances.reports.show', ['month' => '2026-03']))->assertOk();
});

it('financials-view user can access the detail page for a published month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $this->get(route('finances.reports.show', ['month' => '2026-03']))->assertOk();
});

it('financials-view user is forbidden from the detail page of an unpublished month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertForbidden();
});

// == Detail page content == //

it('detail page shows the month heading', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertSee('March 2026');
});

it('detail page shows transactions for the month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create(['name' => 'Infrastructure']);
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 5000,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
        'notes' => 'Hosting bill',
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertSee('Hosting bill');
});

it('detail page does not show transactions from other months', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'notes' => 'March transaction',
        'entered_by' => $user->id,
    ]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-04-10',
        'notes' => 'April transaction',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertSee('March transaction')
        ->assertDontSee('April transaction');
});

it('detail page shows Publish button for treasurer on unpublished month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertSee('Confirm')
        ->assertSee('Publish');
});

it('detail page shows Download PDF button for published month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertSee('Download PDF');
});

it('detail page shows edit and delete buttons for unpublished month for treasurer', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 1000,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertSee('Edit')
        ->assertSee('Delete');
});

it('detail page does not show edit or delete buttons for published month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 1000,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $html = livewire('finances.reports.show', ['month' => '2026-03'])
        ->html();

    // Actions column header and Edit/Delete buttons should not appear
    expect($html)->not->toContain('wire:click="openEditModal(')
        ->not->toContain('wire:click="deleteTransaction(');
});

// == Publish via detail page == //

it('treasurer can publish a month via the detail page', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->call('publish');

    expect(FinancialPeriodReport::whereDate('month', '2026-03-01')->first()?->isPublished())->toBeTrue();
});

it('detail page publish shows error toast when month already published', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    // Action throws RuntimeException which is caught and toasted — no exception bubbles up
    livewire('finances.reports.show', ['month' => '2026-03'])
        ->call('publish')
        ->assertHasNoErrors();
    expect(FinancialPeriodReport::whereDate('month', '2026-03-01')->count())->toBe(1);
});

// == Sequential publish guard (PublishPeriodReport action) == //

it('publish action blocks publishing when an earlier month has unpublished transactions', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();

    // February has transactions but no published report
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-02-15',
        'entered_by' => $user->id,
    ]);

    // March also has transactions
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    // Trying to publish March while February is unpublished should fail
    expect(fn () => PublishPeriodReport::run('2026-03-01', $user))
        ->toThrow(\RuntimeException::class, 'earlier months');
});

it('publish action allows publishing when all earlier months are published', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();

    // February has transactions AND a published report
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-02-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-02-01')->create();

    // March has transactions
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    // Publishing March should succeed
    $report = PublishPeriodReport::run('2026-03-01', $user);
    expect($report->isPublished())->toBeTrue();
});

it('publish action allows publishing the earliest month with no prior months', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    // No prior months with transactions — should succeed
    $report = PublishPeriodReport::run('2026-03-01', $user);
    expect($report->isPublished())->toBeTrue();
});

// == Edit/delete on detail page == //

it('treasurer can delete a transaction from the detail page', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->call('deleteTransaction', $tx->id);

    expect(FinancialTransaction::find($tx->id))->toBeNull();
});

it('treasurer can edit a transaction from the detail page', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'financial_category_id' => $category->id,
        'type' => 'expense',
        'amount' => 1000,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports.show', ['month' => '2026-03'])
        ->call('openEditModal', $tx->id)
        ->set('editAmount', '25.00')
        ->call('updateTransaction');

    expect($tx->fresh()->amount)->toBe(2500);
});
