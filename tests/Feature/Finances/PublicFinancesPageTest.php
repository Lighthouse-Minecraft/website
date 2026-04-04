<?php

declare(strict_types=1);

use App\Enums\MembershipLevel;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPeriodReport;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'public-finances');

// == Public access == //

it('public finances page is accessible without login', function () {
    $this->get(route('finances.public'))
        ->assertOk();
});

it('public finances page is accessible when logged in', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('finances.public'))
        ->assertOk();
});

// == Unpublished data is never shown == //

it('unpublished months do not appear on public page', function () {
    $account = FinancialAccount::factory()->create();
    $user = User::factory()->create();

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);

    // No FinancialPeriodReport → not published
    $component = livewire('finances.public');
    $months = $component->instance()->publishedMonths();

    expect($months)->toBeEmpty();
});

it('only published months appear on public page', function () {
    $account = FinancialAccount::factory()->create();
    $user = User::factory()->create();

    // Published month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    // Unpublished month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 3000,
        'transacted_at' => '2026-04-10',
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.public');
    $months = $component->instance()->publishedMonths();
    $yms = collect($months)->pluck('ym');

    expect($yms)->toContain('2026-03')
        ->not->toContain('2026-04');
});

// == Basic (public) tier: income, expense, net per month == //

it('public tier shows income and expense totals per published month', function () {
    $account = FinancialAccount::factory()->create();
    $user = User::factory()->create();

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 10000,
        'transacted_at' => '2026-03-10',
        'entered_by' => $user->id,
    ]);
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 4000,
        'transacted_at' => '2026-03-15',
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $component = livewire('finances.public');
    $months = $component->instance()->publishedMonths();

    expect($months)->toHaveCount(1);
    expect($months[0]['income'])->toBe(10000);
    expect($months[0]['expense'])->toBe(4000);
    expect($months[0]['net'])->toBe(6000);
    expect($months[0]['categories'])->toBeEmpty(); // no category breakdown for public tier
});

it('unauthenticated user gets public tier with no category breakdown', function () {
    $account = FinancialAccount::factory()->create();
    $creator = User::factory()->create();
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-10',
        'entered_by' => $creator->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $component = livewire('finances.public');

    expect($component->instance()->viewTier())->toBe('public');
    $months = $component->instance()->publishedMonths();
    expect($months[0]['categories'])->toBeEmpty();
});

// == Resident tier: category breakdown == //

it('resident user sees top-level category breakdown', function () {
    $account = FinancialAccount::factory()->create();
    $user = User::factory()->create(['membership_level' => MembershipLevel::Resident]);
    $this->actingAs($user);

    $donations = FinancialCategory::factory()->create(['name' => 'Donations', 'type' => 'income', 'parent_id' => null]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 5000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $donations->id,
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $component = livewire('finances.public');

    expect($component->instance()->viewTier())->toBe('resident');
    $months = $component->instance()->publishedMonths();
    expect($months[0]['categories'])->not->toBeEmpty();
    expect($months[0]['categories'][0]['name'])->toBe('Donations');
    expect($months[0]['categories'][0]['total'])->toBe(5000);
    // Resident tier has no subcategory detail
    expect($months[0]['categories'][0]['subcategories'])->toBeEmpty();
});

// == Staff (financials-view) tier: subcategory breakdown + transaction count == //

it('financials-view staff sees subcategory breakdown with transaction counts', function () {
    $account = FinancialAccount::factory()->create();
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    $infra = FinancialCategory::factory()->create(['name' => 'Infrastructure', 'type' => 'expense', 'parent_id' => null]);
    $hosting = FinancialCategory::factory()->create(['name' => 'Hosting', 'type' => 'expense', 'parent_id' => $infra->id]);

    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 2000,
        'transacted_at' => '2026-03-10',
        'financial_category_id' => $hosting->id,
        'entered_by' => $user->id,
    ]);
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 1500,
        'transacted_at' => '2026-03-15',
        'financial_category_id' => $hosting->id,
        'entered_by' => $user->id,
    ]);
    FinancialPeriodReport::factory()->published()->forMonth('2026-03-01')->create();

    $component = livewire('finances.public');

    expect($component->instance()->viewTier())->toBe('staff');
    $months = $component->instance()->publishedMonths();
    $cats = $months[0]['categories'];

    $infraRow = collect($cats)->firstWhere('name', 'Infrastructure');
    expect($infraRow)->not->toBeNull();
    expect($infraRow['subcategories'])->toHaveCount(1);
    expect($infraRow['subcategories'][0]['name'])->toBe('Hosting');
    expect($infraRow['subcategories'][0]['amount'])->toBe(3500);
    expect($infraRow['subcategories'][0]['count'])->toBe(2);
});

// == Year-to-date totals == //

it('year-to-date totals only include published months', function () {
    $account = FinancialAccount::factory()->create();
    $user = User::factory()->create();

    // Published month
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 8000,
        'transacted_at' => now()->startOfYear()->addDays(15)->toDateString(),
        'entered_by' => $user->id,
    ]);
    $publishedMonth = now()->startOfYear()->format('Y-m-d');
    FinancialPeriodReport::factory()->published()->forMonth($publishedMonth)->create();

    // Unpublished month - should NOT be in YTD
    FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => 3000,
        'transacted_at' => now()->startOfYear()->addMonths(1)->addDays(15)->toDateString(),
        'entered_by' => $user->id,
    ]);

    $component = livewire('finances.public');
    $ytd = $component->instance()->yearToDate();

    expect($ytd['income'])->toBe(8000);
});

// == Empty state == //

it('page renders correctly when no months have been published', function () {
    livewire('finances.public')
        ->assertSee('No published financial reports yet.');
});
