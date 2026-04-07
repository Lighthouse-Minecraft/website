<?php

declare(strict_types=1);

use App\Actions\CreateJournalEntry;
use App\Actions\ParseDollarAmount;
use App\Actions\PostJournalEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialPeriod;
use App\Models\FinancialTag;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'journal');

// == ParseDollarAmount ==

it('parses whole dollar amount', function () {
    expect(ParseDollarAmount::run('10'))->toBe(1000);
});

it('parses amount with one decimal', function () {
    expect(ParseDollarAmount::run('10.0'))->toBe(1000);
});

it('parses amount with two decimals', function () {
    expect(ParseDollarAmount::run('10.00'))->toBe(1000);
});

it('parses sub-dollar amount', function () {
    expect(ParseDollarAmount::run('0.99'))->toBe(99);
});

it('rejects non-numeric input', function () {
    expect(fn () => ParseDollarAmount::run('abc'))->toThrow(\InvalidArgumentException::class);
});

it('rejects negative input', function () {
    expect(fn () => ParseDollarAmount::run('-5'))->toThrow(\InvalidArgumentException::class);
});

// == CreateJournalEntry — income ==

it('CreateJournalEntry creates income entry with correct debit/credit lines', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Donation received',
        amountCents: 5000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $lines = $entry->lines()->with('account')->get();

    $debitLine = $lines->firstWhere('debit', '>', 0);
    $creditLine = $lines->firstWhere('credit', '>', 0);

    expect($debitLine->account_id)->toBe($bank->id);
    expect($debitLine->debit)->toBe(5000);
    expect($creditLine->account_id)->toBe($revenue->id);
    expect($creditLine->credit)->toBe(5000);
    expect($entry->entry_type)->toBe('income');
    expect($entry->status)->toBe('draft');
});

it('CreateJournalEntry creates expense entry with correct debit/credit lines', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $expense = FinancialAccount::factory()->create(['type' => 'expense']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'expense',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Hosting payment',
        amountCents: 2500,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $lines = $entry->lines()->get();

    $debitLine = $lines->firstWhere('debit', '>', 0);
    $creditLine = $lines->firstWhere('credit', '>', 0);

    expect($debitLine->account_id)->toBe($expense->id);
    expect($debitLine->debit)->toBe(2500);
    expect($creditLine->account_id)->toBe($bank->id);
    expect($creditLine->credit)->toBe(2500);
    expect($entry->entry_type)->toBe('expense');
});

it('CreateJournalEntry creates transfer entry with correct debit/credit lines', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $fromAcct = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $toAcct = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);

    // Transfer: primaryAccountId = from-account, bankAccountId = to-account
    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'transfer',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Move funds to savings',
        amountCents: 10000,
        primaryAccountId: $fromAcct->id,
        bankAccountId: $toAcct->id,
        status: 'draft',
    );

    $lines = $entry->lines()->get();

    $debitLine = $lines->firstWhere('debit', '>', 0);
    $creditLine = $lines->firstWhere('credit', '>', 0);

    // Debit to-account, Credit from-account
    expect($debitLine->account_id)->toBe($toAcct->id);
    expect($creditLine->account_id)->toBe($fromAcct->id);
    expect($entry->entry_type)->toBe('transfer');
});

it('CreateJournalEntry attaches tags to entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);
    $tag1 = FinancialTag::factory()->create();
    $tag2 = FinancialTag::factory()->create();

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Tagged income',
        amountCents: 1000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        tagIds: [$tag1->id, $tag2->id],
    );

    expect($entry->tags()->count())->toBe(2);
});

it('CreateJournalEntry stores donor email on income entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Donation',
        amountCents: 1000,
        primaryAccountId: $revenue->id,
        bankAccountId: $bank->id,
        donorEmail: 'donor@example.com',
    );

    expect($entry->donor_email)->toBe('donor@example.com');
});

// == PostJournalEntry ==

it('PostJournalEntry posts a draft entry and makes it immutable', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Draft to post',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    PostJournalEntry::run($user, $entry);

    $entry->refresh();
    expect($entry->status)->toBe('posted');
    expect($entry->posted_at)->not->toBeNull();
    expect($entry->posted_by_id)->toBe($user->id);
});

it('PostJournalEntry rejects already-posted entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Already posted',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    expect(fn () => PostJournalEntry::run($user, $entry))->toThrow(\RuntimeException::class);
});

it('PostJournalEntry rejects posting to a closed period', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'closed']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = FinancialJournalEntry::factory()->create([
        'period_id' => $period->id,
        'status' => 'draft',
        'entry_type' => 'income',
        'created_by_id' => $user->id,
    ]);

    $entry->lines()->createMany([
        ['account_id' => $bank->id, 'debit' => 1000, 'credit' => 0],
        ['account_id' => $rev->id,  'debit' => 0, 'credit' => 1000],
    ]);

    expect(fn () => PostJournalEntry::run($user, $entry))->toThrow(\RuntimeException::class);
});

it('PostJournalEntry rejects unbalanced entries', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = FinancialJournalEntry::factory()->create([
        'period_id' => $period->id,
        'status' => 'draft',
        'entry_type' => 'journal',
        'created_by_id' => $user->id,
    ]);

    // Intentionally unbalanced: 1000 debit, 500 credit
    $entry->lines()->createMany([
        ['account_id' => $bank->id, 'debit' => 1000, 'credit' => 0],
        ['account_id' => $rev->id,  'debit' => 0, 'credit' => 500],
    ]);

    expect(fn () => PostJournalEntry::run($user, $entry))->toThrow(\RuntimeException::class);
});

// == Journal entry list view ==

it('Finance - View user can access journal entries page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.journal.index'))
        ->assertOk();
});

it('non-finance user is forbidden from journal entries page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.journal.index'))
        ->assertForbidden();
});

it('journal entries list shows draft and posted status', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Draft Entry',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-16',
        description: 'Posted Entry',
        amountCents: 2000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    $component = Volt::actingAs($user)->test('finance.journal-entries');

    expect($component->get('entries')->count())->toBe(2);
});

it('journal entries list filters by entry type', function () {
    $user = User::factory()->withRole('Finance - View')->create();
    $recorder = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);
    $expense = FinancialAccount::factory()->create(['type' => 'expense']);

    CreateJournalEntry::run(
        user: $recorder,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Income entry',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
    );

    CreateJournalEntry::run(
        user: $recorder,
        type: 'expense',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Expense entry',
        amountCents: 500,
        primaryAccountId: $expense->id,
        bankAccountId: $bank->id,
    );

    $component = Volt::actingAs($user)->test('finance.journal-entries');
    $component->set('filterType', 'income');

    $entries = $component->get('entries');
    expect($entries->count())->toBe(1);
    expect($entries->first()->entry_type)->toBe('income');
});

it('journal entries list filters by date range', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create();
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-10',
        description: 'Early entry',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
    );

    CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-20',
        description: 'Late entry',
        amountCents: 2000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
    );

    $component = Volt::actingAs($user)->test('finance.journal-entries');
    $component->set('filterDateFrom', '2026-01-15');
    $component->set('filterDateTo', '2026-01-31');

    $entries = $component->get('entries');
    expect($entries->count())->toBe(1);
    expect($entries->first()->description)->toBe('Late entry');
});

// == Create journal entry form ==

it('Finance - Record user can access the create journal entry page', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $this->actingAs($user)
        ->get(route('finance.journal.create'))
        ->assertOk();
});

it('Finance - View user is forbidden from create journal entry page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.journal.create'))
        ->assertForbidden();
});

it('create journal entry form saves as draft', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $revenue = FinancialAccount::factory()->create(['type' => 'revenue']);

    // Set the period dates to cover today
    $period->update([
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    Volt::actingAs($user)
        ->test('finance.create-journal-entry')
        ->set('entryType', 'income')
        ->set('date', now()->format('Y-m-d'))
        ->set('description', 'Test Donation')
        ->set('amount', '25.00')
        ->set('revenueAccountId', $revenue->id)
        ->set('bankAccountId', $bank->id)
        ->call('save', 'draft');

    expect(FinancialJournalEntry::where('description', 'Test Donation')->exists())->toBeTrue();
    expect(FinancialJournalEntry::where('description', 'Test Donation')->first()->status)->toBe('draft');
});

it('create journal entry form validates required fields', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    Volt::actingAs($user)
        ->test('finance.create-journal-entry')
        ->set('entryType', 'income')
        ->call('save', 'draft')
        ->assertHasErrors(['description', 'amount', 'revenueAccountId', 'bankAccountId']);
});

it('entries in closed periods cannot be posted via the form', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'closed']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    // Manually set dates to cover today so the period resolver finds it
    $period->update([
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);

    $component = Volt::actingAs($user)
        ->test('finance.create-journal-entry')
        ->set('entryType', 'income')
        ->set('date', now()->format('Y-m-d'))
        ->set('description', 'Closed Period Entry')
        ->set('amount', '10.00')
        ->set('revenueAccountId', $rev->id)
        ->set('bankAccountId', $bank->id)
        ->call('save', 'posted');

    $component->assertHasErrors(['date']);
    expect(FinancialJournalEntry::where('description', 'Closed Period Entry')->exists())->toBeFalse();
});

// == Edit Draft Journal Entry ==

it('Finance - Record user can load edit page for a draft guided entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->format('Y-m-d'),
        description: 'Original Income',
        amountCents: 5000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    $component = Volt::actingAs($user)
        ->test('finance.edit-journal-entry', ['entryId' => $entry->id]);

    expect($component->get('description'))->toBe('Original Income');
    expect($component->get('amount'))->toBe('50.00');
    expect($component->get('entryType'))->toBe('income');
    expect($component->get('revenueAccountId'))->toBe($rev->id);
    expect($component->get('bankAccountId'))->toBe($bank->id);
});

it('Finance - Record user can save changes to a draft income entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: '2026-01-15',
        description: 'Old Description',
        amountCents: 5000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    Volt::actingAs($user)
        ->test('finance.edit-journal-entry', ['entryId' => $entry->id])
        ->set('description', 'Updated Description')
        ->set('amount', '75.00')
        ->call('save');

    $updated = $entry->fresh()->load('lines');
    expect($updated->description)->toBe('Updated Description');
    expect($updated->lines->sum('debit'))->toBe(7500);
    expect($updated->status)->toBe('draft');
});

it('Finance - Record user can delete a draft entry from the edit page', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->format('Y-m-d'),
        description: 'Delete Me',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'draft',
    );

    Volt::actingAs($user)
        ->test('finance.edit-journal-entry', ['entryId' => $entry->id])
        ->call('delete');

    expect(FinancialJournalEntry::find($entry->id))->toBeNull();
});

it('edit page redirects when trying to edit a posted entry', function () {
    $user = User::factory()->withRole('Finance - Record')->create();
    $period = FinancialPeriod::factory()->create(['status' => 'open']);
    $bank = FinancialAccount::factory()->create(['type' => 'asset', 'is_bank_account' => true]);
    $rev = FinancialAccount::factory()->create(['type' => 'revenue']);

    $entry = CreateJournalEntry::run(
        user: $user,
        type: 'income',
        periodId: $period->id,
        date: $period->start_date->format('Y-m-d'),
        description: 'Posted Entry',
        amountCents: 1000,
        primaryAccountId: $rev->id,
        bankAccountId: $bank->id,
        status: 'posted',
    );

    Volt::actingAs($user)
        ->test('finance.edit-journal-entry', ['entryId' => $entry->id])
        ->assertRedirect(route('finance.journal.index'));
});
