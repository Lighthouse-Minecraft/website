<?php

declare(strict_types=1);

use App\Actions\PublishPeriodReport;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'reports');

// == Route Access == //

it('treasurer can access the reports page', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    $this->get(route('finances.reports'))->assertOk();
});

it('financials-view user can access the reports page', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    $this->get(route('finances.reports'))->assertOk();
});

// == PublishPeriodReport Action == //

it('publish action creates a period report with published_at set', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    PublishPeriodReport::run('2026-03-01', $user);

    $report = FinancialPeriodReport::whereDate('month', '2026-03-01')->first();
    expect($report)->not->toBeNull()
        ->and($report->published_at)->not->toBeNull()
        ->and($report->published_by)->toBe($user->id);
});

it('publish action fails when no transactions exist in the month', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();

    expect(fn () => PublishPeriodReport::run('2026-03-01', $user))
        ->toThrow(\RuntimeException::class);
});

it('publish action fails when month is already published', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    PublishPeriodReport::run('2026-03-01', $user);

    expect(fn () => PublishPeriodReport::run('2026-03-01', $user))
        ->toThrow(\RuntimeException::class);
});

// == Livewire Component == //

it('treasurer sees publish button for unpublished month with transactions', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.reports');
    $months = $component->instance()->months();

    $march = collect($months)->firstWhere('ym', '2026-03');
    expect($march)->not->toBeNull()
        ->and($march['published'])->toBeFalse();
});

it('treasurer can publish a month via confirmPublish', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports')
        ->call('openPublishModal', '2026-03-01')
        ->call('confirmPublish');

    expect(FinancialPeriodReport::whereDate('month', '2026-03-01')->first()?->isPublished())->toBeTrue();
});

it('published month shows as published in report list', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $component = livewire('finances.reports');
    $months = $component->instance()->months();

    $march = collect($months)->firstWhere('ym', '2026-03');
    expect($march['published'])->toBeTrue();
});

it('view-only user cannot publish a month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    livewire('finances.reports')
        ->call('openPublishModal', '2026-03-01')
        ->assertForbidden();

    expect(FinancialPeriodReport::count())->toBe(0);
});

// == Immutability (already enforced in actions, verify end-to-end) == //

it('transactions in a published month cannot be edited via dashboard', function () {
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

    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    livewire('finances.dashboard')
        ->set('editTxId', $tx->id)
        ->set('editType', 'expense')
        ->set('editAccountId', (string) $account->id)
        ->set('editAmount', '9999')
        ->set('editDate', '2026-03-15')
        ->set('editCategoryId', (string) $category->id)
        ->set('editSubcategoryId', '')
        ->set('editNotes', '')
        ->set('editTagIds', [])
        ->call('updateTransaction');

    expect($tx->fresh()->amount)->toBe(1000);
});

it('transactions in a published month cannot be deleted via dashboard', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    livewire('finances.dashboard')
        ->call('deleteTransaction', $tx->id);

    expect(FinancialTransaction::find($tx->id))->not->toBeNull();
});
