<?php

declare(strict_types=1);

use App\Actions\CloseFinancialPeriod;
use App\Actions\CreateJournalEntry;
use App\Enums\MembershipLevel;
use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use App\Models\FinancialReconciliation;
use App\Models\FinancialRestrictedFund;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'community-finance');

// Helper: mark all bank accounts as reconciled for a period
function reconcileAllForPeriod(FinancialPeriod $period, User $user): void
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
function ensureNetAssets(): void
{
    FinancialAccount::where('type', 'net_assets')->where('subtype', 'unrestricted')->firstOrCreate(
        ['type' => 'net_assets', 'subtype' => 'unrestricted'],
        ['code' => 30099, 'name' => 'Net Assets — Unrestricted', 'normal_balance' => 'credit', 'fund_type' => 'unrestricted', 'is_bank_account' => false, 'is_active' => true]
    );
}

// == Access control ==

it('Resident user can access community finance page', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();

    $this->actingAs($user)
        ->get(route('finance.community.index'))
        ->assertOk();
});

it('Traveler user cannot access community finance page', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();

    $this->actingAs($user)
        ->get(route('finance.community.index'))
        ->assertForbidden();
});

it('Stowaway user cannot access community finance page', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();

    $this->actingAs($user)
        ->get(route('finance.community.index'))
        ->assertForbidden();
});

it('unauthenticated user cannot access community finance page', function () {
    $this->get(route('finance.community.index'))
        ->assertRedirect();
});

// == Shows only closed periods ==

it('shows closed periods and excludes open periods', function () {
    $financeUser = User::factory()->withRole('Finance - Record')->create();
    ensureNetAssets();

    $closedPeriod = FinancialPeriod::factory()->create(['name' => 'October 2025', 'status' => 'open']);
    reconcileAllForPeriod($closedPeriod, $financeUser);
    CloseFinancialPeriod::run($closedPeriod, $financeUser);

    $openPeriod = FinancialPeriod::factory()->create(['name' => 'November 2025', 'status' => 'open']);

    $resident = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
    $component = Volt::actingAs($resident)->test('finance.community-finance');

    $closedPeriods = $component->get('closedPeriods');
    $names = $closedPeriods->pluck('name');

    expect($names)->toContain('October 2025');
    expect($names)->not->toContain('November 2025');
});

// == Period summary data ==

it('shows revenue totals grouped by account for closed periods', function () {
    $financeUser = User::factory()->withRole('Finance - Record')->create();
    ensureNetAssets();

    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit', 'name' => 'Test Revenue']);

    CreateJournalEntry::run(
        user: $financeUser,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Community donation',
        amountCents: 25000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    reconcileAllForPeriod($period, $financeUser);
    CloseFinancialPeriod::run($period, $financeUser);

    $resident = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
    $component = Volt::actingAs($resident)->test('finance.community-finance');

    $revenueSummary = $component->instance()->getPeriodRevenueSummary($period->id);

    expect($revenueSummary)->not->toBeEmpty();
    $row = collect($revenueSummary)->firstWhere('account_id', $revenue->id);
    expect($row['total'])->toBe(25000);
});

it('shows expense totals grouped by account for closed periods', function () {
    $financeUser = User::factory()->withRole('Finance - Record')->create();
    ensureNetAssets();

    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit', 'name' => 'Test Expense']);

    CreateJournalEntry::run(
        user: $financeUser,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Hosting',
        amountCents: 10000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    reconcileAllForPeriod($period, $financeUser);
    CloseFinancialPeriod::run($period, $financeUser);

    $resident = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
    $component = Volt::actingAs($resident)->test('finance.community-finance');

    $expenseSummary = $component->instance()->getPeriodExpenseSummary($period->id);

    expect($expenseSummary)->not->toBeEmpty();
    $row = collect($expenseSummary)->firstWhere('account_id', $expense->id);
    expect($row['total'])->toBe(10000);
});

it('shows restricted fund summaries for periods with restricted activity', function () {
    $financeUser = User::factory()->withRole('Finance - Record')->create();
    ensureNetAssets();

    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'is_active' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);
    $fund = FinancialRestrictedFund::factory()->create(['name' => 'Server Fund']);

    CreateJournalEntry::run(
        user: $financeUser,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Restricted donation',
        amountCents: 15000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
        restrictedFundId: $fund->id,
    );

    reconcileAllForPeriod($period, $financeUser);
    CloseFinancialPeriod::run($period, $financeUser);

    $resident = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
    $component = Volt::actingAs($resident)->test('finance.community-finance');

    $fundSummary = $component->instance()->getPeriodRestrictedFundSummary($period->id);

    expect($fundSummary)->not->toBeEmpty();
    $row = collect($fundSummary)->firstWhere('fund_id', $fund->id);
    expect($row['name'])->toBe('Server Fund');
    expect($row['received'])->toBe(15000);
});

// == Finance staff link ==

it('Finance - View user sees Staff Finance Portal link', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($user)->test('finance.community-finance');

    $component->assertSee('Staff Finance Portal');
});

it('Resident user without finance role does not see Staff Finance Portal link', function () {
    $user = User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();

    $component = Volt::actingAs($user)->test('finance.community-finance');

    $component->assertDontSee('Staff Finance Portal');
});
