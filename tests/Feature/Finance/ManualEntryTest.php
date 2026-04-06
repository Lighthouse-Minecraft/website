<?php

declare(strict_types=1);

use App\Actions\CreateJournalEntry;
use App\Actions\CreateReversingEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'manual-entry');

// == CreateReversingEntry action ==

it('CreateReversingEntry creates a draft entry with inverted lines', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $original = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Donation',
        amountCents: 5000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $reversing = CreateReversingEntry::run($user, $original);

    expect($reversing->status)->toBe('draft');
    expect($reversing->reverses_entry_id)->toBe($original->id);

    $origLines = $original->lines->sortBy('account_id')->values();
    $revLines = $reversing->lines()->get()->sortBy('account_id')->values();

    foreach ($origLines as $i => $origLine) {
        expect($revLines[$i]->account_id)->toBe($origLine->account_id);
        expect($revLines[$i]->debit)->toBe($origLine->credit);
        expect($revLines[$i]->credit)->toBe($origLine->debit);
    }
});

it('CreateReversingEntry sets reverses_entry_id on new entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $exp = FinancialAccount::factory()->create(['type' => 'expense']);

    $original = CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Hosting bill',
        amountCents: 2000,
        primaryAccountId: $exp->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $reversing = CreateReversingEntry::run($user, $original);

    expect($reversing->reverses_entry_id)->toBe($original->id);
    expect($reversing->fresh()->reversesEntry->id)->toBe($original->id);
});

it('CreateReversingEntry rejects reversing a draft entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $draft = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Draft entry',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    expect(fn () => CreateReversingEntry::run($user, $draft))->toThrow(\RuntimeException::class);
});

it('original entry reversedBy relationship points to the reversing entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $original = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'To be reversed',
        amountCents: 3000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $reversing = CreateReversingEntry::run($user, $original);

    expect($original->fresh()->reversedBy->id)->toBe($reversing->id);
});

// == Manual journal entry form ==

it('Finance - Record user can access the manual journal entry page', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $this->actingAs($user)
        ->get(route('finance.journal.create-manual'))
        ->assertOk();
});

it('Finance - View user is forbidden from manual journal entry page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.journal.create-manual'))
        ->assertForbidden();
});

it('manual entry form validates that all lines have accounts and amounts', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    Volt::actingAs($user)
        ->test('finance.create-manual-entry')
        ->call('save', 'draft')
        ->assertHasErrors(['lines.0.account_id', 'lines.0.amount', 'lines.1.account_id', 'lines.1.amount']);
});

it('manual entry form blocks posting when debits do not equal credits', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset']);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $period->update([
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    $component = Volt::actingAs($user)
        ->test('finance.create-manual-entry')
        ->set('date', now()->format('Y-m-d'))
        ->set('description', 'Unbalanced entry')
        ->set('lines', [
            ['account_id' => $bank->id, 'side' => 'debit',  'amount' => '100.00', 'memo' => ''],
            ['account_id' => $rev->id,  'side' => 'credit', 'amount' => '50.00',  'memo' => ''],
        ])
        ->call('save', 'posted');

    $component->assertHasErrors(['lines']);
    expect(FinancialJournalEntry::where('description', 'Unbalanced entry')->exists())->toBeFalse();
});

it('manual entry form saves balanced entry as draft', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset']);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $period->update([
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    Volt::actingAs($user)
        ->test('finance.create-manual-entry')
        ->set('date', now()->format('Y-m-d'))
        ->set('description', 'Manual balanced entry')
        ->set('lines', [
            ['account_id' => $bank->id, 'side' => 'debit',  'amount' => '75.00', 'memo' => ''],
            ['account_id' => $rev->id,  'side' => 'credit', 'amount' => '75.00', 'memo' => ''],
        ])
        ->call('save', 'draft');

    $entry = FinancialJournalEntry::where('description', 'Manual balanced entry')->first();
    expect($entry)->not->toBeNull();
    expect($entry->status)->toBe('draft');
    expect($entry->entry_type)->toBe('journal');
    expect($entry->lines()->sum('debit'))->toBe(7500);
    expect($entry->lines()->sum('credit'))->toBe(7500);
});

it('manual entry form can post a balanced entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset']);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $period->update([
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    Volt::actingAs($user)
        ->test('finance.create-manual-entry')
        ->set('date', now()->format('Y-m-d'))
        ->set('description', 'Posted manual entry')
        ->set('lines', [
            ['account_id' => $bank->id, 'side' => 'debit',  'amount' => '200.00', 'memo' => ''],
            ['account_id' => $rev->id,  'side' => 'credit', 'amount' => '200.00', 'memo' => ''],
        ])
        ->call('save', 'posted');

    $entry = FinancialJournalEntry::where('description', 'Posted manual entry')->first();
    expect($entry)->not->toBeNull();
    expect($entry->status)->toBe('posted');
});

// == Reverse button in journal entries list ==

it('Finance - Record user can trigger a reversing entry from the list', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $original = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Reverse me',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    Volt::actingAs($user)
        ->test('finance.journal-entries')
        ->call('reverse', $original->id);

    expect(FinancialJournalEntry::where('reverses_entry_id', $original->id)->exists())->toBeTrue();
});

it('Finance - View user cannot trigger reversing entries', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $recorder = User::factory()->withRole('Finance - Record')->create();
    $original = CreateJournalEntry::run(
        user: $recorder,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Cannot reverse this',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    Volt::actingAs($user)
        ->test('finance.journal-entries')
        ->call('reverse', $original->id)
        ->assertForbidden();
});
