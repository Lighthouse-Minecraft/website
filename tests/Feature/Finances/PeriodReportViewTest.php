<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'reports-view');

// == Published vs unpublished visibility == //

it('view-only user sees only published months in the list', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    // Unpublished month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-02-15',
        'entered_by' => $user->id,
    ]);

    // Published month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $component = livewire('finances.reports');
    $months = $component->instance()->months();
    $yms = collect($months)->pluck('ym');

    expect($yms)->toContain('2026-03')
        ->not->toContain('2026-02');
});

it('treasurer sees both published and unpublished months', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    // Unpublished month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-02-15',
        'entered_by' => $user->id,
    ]);

    // Published month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $component = livewire('finances.reports');
    $months = $component->instance()->months();
    $yms = collect($months)->pluck('ym');

    expect($yms)->toContain('2026-03')
        ->toContain('2026-02');
});

// == View modal == //

it('view-only user can access the detail page for a published month', function () {
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
        ->assertOk()
        ->assertSet('isPublished', true);
});

it('view-only user cannot access the detail page for an unpublished month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    // No published report for March
    livewire('finances.reports.show', ['month' => '2026-03'])
        ->assertForbidden();
});

// == PDF download route == //

it('financials-view user can download a PDF for a published month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $response = $this->get(route('finances.reports.pdf', ['month' => '2026-03']));
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('PDF download returns 404 for unpublished month', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $account = FinancialAccount::factory()->create();
    $this->actingAs($user);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    $this->get(route('finances.reports.pdf', ['month' => '2026-03']))
        ->assertNotFound();
});

it('unauthenticated user cannot download PDF', function () {
    $this->get(route('finances.reports.pdf', ['month' => '2026-03']))
        ->assertRedirect(route('login'));
});
