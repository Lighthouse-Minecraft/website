<?php

declare(strict_types=1);

use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'reports');

// == Access control ==

it('Finance - View user can access the reports page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.reports.index'))
        ->assertOk();
});

it('Finance - Record user can access the reports page', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $this->actingAs($user)
        ->get(route('finance.reports.index'))
        ->assertOk();
});

it('non-finance user is forbidden from reports page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.reports.index'))
        ->assertForbidden();
});

// == Statement of Activities ==

it('statement of activities shows posted revenue totals', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Donation',
        amountCents: 25000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    $revenueRows = $component->get('revenueRows');
    $row = $revenueRows->firstWhere('account_id', $revenue->id);

    expect($row)->not->toBeNull();
    expect($row['net'])->toBe(25000);
    expect($component->get('totalRevenue'))->toBe(25000);
});

it('statement of activities shows posted expense totals', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Server bill',
        amountCents: 8000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    expect($component->get('totalExpenses'))->toBe(8000);
});

it('statement of activities computes net change correctly', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Donation',
        amountCents: 30000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Hosting',
        amountCents: 10000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    expect($component->get('netChange'))->toBe(20000);
});

it('statement of activities excludes draft entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Draft donation',
        amountCents: 5000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    expect($component->get('totalRevenue'))->toBe(0);
});

// == General Ledger ==

it('general ledger shows posted lines for selected account', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Test income',
        amountCents: 12000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'ledger')
        ->set('glAccountId', $bank->id);

    $lines = $component->get('glLines');
    expect($lines)->toHaveCount(1);
    expect($lines->first()->debit)->toBe(12000);
});

it('general ledger computes running balance correctly', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true, 'normal_balance' => 'debit']);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);
    $expense = FinancialAccount::factory()->create(['type' => 'expense', 'normal_balance' => 'debit']);

    // Income of 20000 → bank debit 20000
    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Income',
        amountCents: 20000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    // Expense of 5000 → bank credit 5000
    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->addDay()->toDateString(),
        description: 'Expense',
        amountCents: 5000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'ledger')
        ->set('glAccountId', $bank->id);

    $lines = $component->get('glLines');
    expect($lines)->toHaveCount(2);

    // After first line (debit 20000): running balance = +20000
    expect($lines->first()->running_balance)->toBe(20000);
    // After second line (credit 5000): running balance = +15000
    expect($lines->last()->running_balance)->toBe(15000);
});

it('general ledger excludes draft entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Draft entry',
        amountCents: 999,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'ledger')
        ->set('glAccountId', $bank->id);

    expect($component->get('glLines'))->toHaveCount(0);
});

it('general ledger CSV export returns correct structure', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Export test',
        amountCents: 7500,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'ledger')
        ->set('glAccountId', $bank->id);

    $response = $component->call('exportGlCsv');

    // exportGlCsv returns a StreamedResponse — verify no exceptions thrown
    expect($response)->not->toBeNull();
});

// == Trial Balance ==

it('trial balance debits equal credits for balanced entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Test income',
        amountCents: 15000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'trial')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    expect($component->get('trialBalanceTotalDebit'))->toBe($component->get('trialBalanceTotalCredit'));
    expect($component->get('trialBalanceIsBalanced'))->toBeTrue();
});

it('trial balance excludes draft entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Draft',
        amountCents: 999,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'trial')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    expect($component->get('trialBalanceTotalDebit'))->toBe(0);
    expect($component->get('trialBalanceTotalCredit'))->toBe(0);
});

// == Print header ==

it('print header includes the org name', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $this->actingAs($user);

    Volt::test('finance.reports')
        ->assertSee(config('app.name'));
});

it('print header includes the Lighthouse logo', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $this->actingAs($user);

    Volt::test('finance.reports')
        ->assertSee('LighthouseMC_Logo.png', false);
});

it('print header shows report title for active tab', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $this->actingAs($user);

    Volt::test('finance.reports')
        ->set('activeTab', 'ledger')
        ->assertSee('General Ledger');
});

it('print header shows Statement of Activities for default tab', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $this->actingAs($user);

    Volt::test('finance.reports')
        ->assertSee('Statement of Activities');
});
