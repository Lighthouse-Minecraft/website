<?php

declare(strict_types=1);

use App\Actions\CloseFinancialPeriod;
use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialPeriod;
use App\Models\FinancialReconciliation;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'public-dashboard');

// Helper: mark all bank accounts as reconciled for a period
function reconcileAllForPublicTest(FinancialPeriod $period, User $user): void
{
    FinancialAccount::where('is_bank_account', true)
        ->where('is_active', true)
        ->each(function ($bank) use ($period, $user) {
            FinancialReconciliation::firstOrCreate(
                ['account_id' => $bank->id, 'period_id' => $period->id],
                [
                    'statement_date' => $period->end_date->toDateString(),
                    'statement_ending_balance' => 0,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_by_id' => $user->id,
                ]
            );
        });
}

// Helper: ensure net assets accounts exist
function ensureNetAssetsForPublic(): void
{
    FinancialAccount::where('type', 'net_assets')->where('subtype', 'unrestricted')->firstOrCreate(
        ['type' => 'net_assets', 'subtype' => 'unrestricted'],
        ['code' => 30098, 'name' => 'Net Assets — Unrestricted', 'normal_balance' => 'credit', 'fund_type' => 'unrestricted', 'is_bank_account' => false, 'is_active' => true]
    );
}

// == Access control ==

it('unauthenticated guest can access public finance dashboard', function () {
    $this->get(route('finance.public.index'))
        ->assertOk();
});

it('authenticated user can access public finance dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.public.index'))
        ->assertOk();
});

// == Shows only closed periods ==

it('public dashboard shows at most 3 closed periods', function () {
    $financeUser = User::factory()->withRole('Finance - Record')->create();
    ensureNetAssetsForPublic();

    // Create 4 closed periods
    for ($i = 1; $i <= 4; $i++) {
        $period = FinancialPeriod::factory()->create(['status' => 'open', 'fiscal_year' => 2025, 'month_number' => $i]);
        reconcileAllForPublicTest($period, $financeUser);
        CloseFinancialPeriod::run($period, $financeUser);
    }

    $component = Volt::test('finance.public-dashboard');
    expect($component->get('closedPeriods'))->toHaveCount(3);
});

it('public dashboard excludes open and reconciling periods', function () {
    $openPeriod = FinancialPeriod::factory()->create(['status' => 'open', 'name' => 'Open Period']);

    $component = Volt::test('finance.public-dashboard');
    $names = $component->get('closedPeriods')->pluck('name');

    expect($names)->not->toContain('Open Period');
});

it('shows fewer than 3 months gracefully when not enough closed periods', function () {
    // No closed periods
    $component = Volt::test('finance.public-dashboard');
    expect($component->get('closedPeriods'))->toHaveCount(0);
    $component->assertSee('No financial data available yet');
});

// == Income / expense totals ==

it('shows correct income total for a closed period', function () {
    $financeUser = User::factory()->withRole('Finance - Record')->create();
    ensureNetAssetsForPublic();

    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $financeUser,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Public donation',
        amountCents: 12000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    reconcileAllForPublicTest($period, $financeUser);
    CloseFinancialPeriod::run($period, $financeUser);

    $component = Volt::test('finance.public-dashboard');
    expect($component->instance()->getPeriodIncome($period->id))->toBe(12000);
});

// == Donation goal ==

it('donation goal uses budget amounts for donation accounts', function () {
    $revenue = FinancialAccount::factory()->create([
        'type' => 'revenue',
        'subtype' => 'donations',
        'normal_balance' => 'credit',
    ]);

    // Create an open period that covers today
    $period = FinancialPeriod::factory()->create([
        'status' => 'open',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    FinancialBudget::create([
        'account_id' => $revenue->id,
        'period_id' => $period->id,
        'amount' => 6000, // $60.00
    ]);

    $component = Volt::test('finance.public-dashboard');

    expect($component->get('donationGoalCents'))->toBe(6000);
});

it('donation progress tracks posted income for donation accounts in current period', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $revenue = FinancialAccount::factory()->create([
        'type' => 'revenue',
        'subtype' => 'donations',
        'normal_balance' => 'credit',
    ]);

    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);

    $period = FinancialPeriod::factory()->create([
        'status' => 'open',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    FinancialBudget::create([
        'account_id' => $revenue->id,
        'period_id' => $period->id,
        'amount' => 10000,
    ]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: now()->toDateString(),
        description: 'Donation received',
        amountCents: 4000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $component = Volt::test('finance.public-dashboard');

    expect($component->get('donationProgressCents'))->toBe(4000);
    expect($component->get('donationGoalPercent'))->toBe(40);
});

it('donation goal percent caps at 100 when goal is exceeded', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $revenue = FinancialAccount::factory()->create([
        'type' => 'revenue',
        'subtype' => 'donations',
        'normal_balance' => 'credit',
    ]);

    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);

    $period = FinancialPeriod::factory()->create([
        'status' => 'open',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    FinancialBudget::create([
        'account_id' => $revenue->id,
        'period_id' => $period->id,
        'amount' => 5000,
    ]);

    // Post more than the goal
    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: now()->toDateString(),
        description: 'Generous donation',
        amountCents: 8000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $component = Volt::test('finance.public-dashboard');

    expect($component->get('donationGoalPercent'))->toBe(100);
});
