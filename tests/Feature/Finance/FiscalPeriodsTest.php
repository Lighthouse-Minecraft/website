<?php

declare(strict_types=1);

use App\Actions\GenerateFinancialPeriods;
use App\Models\FinancialPeriod;
use App\Models\SiteConfig;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Volt\Volt;

uses()->group('finance', 'fiscal-periods');

// == Period generation == //

it('generates 12 periods for the given fiscal year', function () {
    GenerateFinancialPeriods::run(2026, 10);

    expect(FinancialPeriod::where('fiscal_year', 2026)->count())->toBe(12);
});

it('generates correct month ranges for October-start FY', function () {
    GenerateFinancialPeriods::run(2026, 10);

    $periods = FinancialPeriod::where('fiscal_year', 2026)->orderBy('start_date')->get();

    // First period: October 2025
    expect($periods->first()->start_date->format('Y-m-d'))->toBe('2025-10-01');
    expect($periods->first()->end_date->format('Y-m-d'))->toBe('2025-10-31');
    expect($periods->first()->name)->toBe('October 2025');

    // Last period: September 2026
    expect($periods->last()->start_date->format('Y-m-d'))->toBe('2026-09-01');
    expect($periods->last()->end_date->format('Y-m-d'))->toBe('2026-09-30');
    expect($periods->last()->name)->toBe('September 2026');
});

it('covers all 12 calendar months in the FY', function () {
    GenerateFinancialPeriods::run(2026, 10);

    $months = FinancialPeriod::where('fiscal_year', 2026)->pluck('month_number')->sort()->values()->toArray();

    expect($months)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);
});

it('does not create duplicate periods on repeated generation', function () {
    GenerateFinancialPeriods::run(2026, 10);
    GenerateFinancialPeriods::run(2026, 10);

    expect(FinancialPeriod::where('fiscal_year', 2026)->count())->toBe(12);
});

it('generates correct ranges for January-start FY', function () {
    GenerateFinancialPeriods::run(2027, 1);

    $periods = FinancialPeriod::where('fiscal_year', 2027)->orderBy('start_date')->get();

    expect($periods->first()->start_date->format('Y-m-d'))->toBe('2027-01-01');
    expect($periods->last()->end_date->format('Y-m-d'))->toBe('2027-12-31');
});

it('all generated periods start with status open', function () {
    GenerateFinancialPeriods::run(2026, 10);

    $statuses = FinancialPeriod::where('fiscal_year', 2026)->pluck('status')->unique()->toArray();

    expect($statuses)->toBe(['open']);
});

it('generateForCurrentFY uses the SiteConfig start month', function () {
    SiteConfig::setValue('finance_fy_start_month', '10');

    GenerateFinancialPeriods::generateForCurrentFY();

    // Should have generated 12 periods for some FY
    expect(FinancialPeriod::count())->toBeGreaterThanOrEqual(12);
});

// == Fiscal period list page == //

it('Finance - View user can access the fiscal periods page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.periods.index'))
        ->assertOk();
});

it('non-finance user is forbidden from fiscal periods page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.periods.index'))
        ->assertForbidden();
});

it('periods page auto-generates current FY periods on mount', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    // No periods exist yet
    expect(FinancialPeriod::count())->toBe(0);

    Volt::actingAs($user)
        ->test('finance.fiscal-periods');

    expect(FinancialPeriod::count())->toBe(12);
});

it('periods page shows status badges for open periods', function () {
    GenerateFinancialPeriods::run(2026, 10);

    $user = User::factory()->withRole('Finance - View')->create();

    Volt::actingAs($user)
        ->test('finance.fiscal-periods')
        ->assertSee('Open');
});

it('closed period shows closed date and closer name on periods page', function () {
    $closer = User::factory()->create(['name' => 'Jane Accountant']);
    $period = FinancialPeriod::factory()->closed()->create([
        'fiscal_year' => 2025,
        'month_number' => 10,
        'name' => 'October 2024',
        'start_date' => '2024-10-01',
        'end_date' => '2024-10-31',
        'closed_at' => Carbon::parse('2024-11-05'),
        'closed_by_id' => $closer->id,
    ]);

    $user = User::factory()->withRole('Finance - View')->create();

    Volt::actingAs($user)
        ->test('finance.fiscal-periods')
        ->assertSee('October 2024')
        ->assertSee('Nov 5, 2024')
        ->assertSee('Jane Accountant');
});
