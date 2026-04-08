<?php

declare(strict_types=1);

use App\Actions\CreateJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialPeriod;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'balance-sheet-cash-flow');

// == Statement of Financial Position (Balance Sheet) ==

it('balance sheet total assets equals total net assets when balanced', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    // Find or create net assets accounts (seeded by migration)
    $netAssetsUnrestricted = FinancialAccount::where('type', 'net_assets')->where('subtype', 'unrestricted')->first()
        ?? FinancialAccount::factory()->create([
            'type' => 'net_assets',
            'subtype' => 'unrestricted',
            'normal_balance' => 'credit',
        ]);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Donation',
        amountCents: 20000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'balance-sheet')
        ->set('bsAsOfDate', $period->end_date->toDateString());

    // Asset balance should include the bank account debit
    $assetRows = $component->get('bsAssetRows');
    $bankRow = $assetRows->firstWhere('account_id', $bank->id);
    expect($bankRow['balance'])->toBe(20000);

    expect($component->get('bsTotalAssets'))->toBeGreaterThanOrEqual(20000);
});

it('balance sheet shows unrestricted and restricted net assets separately', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    FinancialAccount::where('type', 'net_assets')->where('subtype', 'unrestricted')->firstOrCreate(
        ['type' => 'net_assets', 'subtype' => 'unrestricted'],
        ['code' => 30001, 'name' => 'Net Assets — Unrestricted', 'normal_balance' => 'credit', 'fund_type' => 'unrestricted', 'is_bank_account' => false, 'is_active' => true]
    );
    FinancialAccount::where('type', 'net_assets')->where('subtype', 'restricted')->firstOrCreate(
        ['type' => 'net_assets', 'subtype' => 'restricted'],
        ['code' => 31001, 'name' => 'Net Assets — Restricted', 'normal_balance' => 'credit', 'fund_type' => 'restricted', 'is_bank_account' => false, 'is_active' => true]
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'balance-sheet');

    // Both properties should be accessible without error
    expect($component->get('bsNetAssetsUnrestricted'))->toBeInt();
    expect($component->get('bsNetAssetsRestricted'))->toBeInt();
});

it('balance sheet excludes draft entries from asset balances', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue', 'normal_balance' => 'credit']);

    // Draft entry — should NOT appear in balance sheet
    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Draft donation',
        amountCents: 9999,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'balance-sheet')
        ->set('bsAsOfDate', now()->toDateString());

    $assetRows = $component->get('bsAssetRows');
    $bankRow = $assetRows->firstWhere('account_id', $bank->id);

    // bank should not appear in the balance (draft excluded)
    expect($bankRow)->toBeNull();
});

// == Statement of Cash Flows ==

it('cash flow net change equals revenue minus expenses', function () {
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
        description: 'Revenue',
        amountCents: 50000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: $period->start_date->toDateString(),
        description: 'Expense',
        amountCents: 15000,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'cash-flow')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    expect($component->get('cashInflows'))->toBe(50000);
    expect($component->get('cashOutflows'))->toBe(15000);
    expect($component->get('netCashChange'))->toBe(35000);
});

it('cash flow excludes draft entries', function () {
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
        amountCents: 12345,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $viewer = User::factory()->withRole('Finance - View')->create();

    $component = Volt::actingAs($viewer)->test('finance.reports')
        ->set('activeTab', 'cash-flow')
        ->set('filterFyYear', $period->fiscal_year)
        ->set('filterPeriodId', $period->id);

    expect($component->get('cashInflows'))->toBe(0);
    expect($component->get('netCashChange'))->toBe(0);
});
