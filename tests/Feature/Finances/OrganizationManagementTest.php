<?php

declare(strict_types=1);

use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialOrganization;
use App\Models\FinancialTransaction;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'organizations');

// == Transaction with Organization == //

it('financials-treasurer can record a transaction with an organization', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $org = FinancialOrganization::factory()->create(['name' => 'AWS', 'created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'expense')
        ->set('accountId', (string) $account->id)
        ->set('amount', '50.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->set('organizationId', $org->id)
        ->set('organizationName', $org->name)
        ->call('submitTransaction');

    $this->assertDatabaseHas('financial_transactions', [
        'account_id' => $account->id,
        'amount' => 5000,
        'organization_id' => $org->id,
    ]);
});

it('financials-treasurer can record a transaction without an organization', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('type', 'expense')
        ->set('accountId', (string) $account->id)
        ->set('amount', '20.00')
        ->set('transactedAt', '2026-04-01')
        ->set('categoryId', (string) $category->id)
        ->call('submitTransaction');

    $tx = FinancialTransaction::latest()->first();
    expect($tx->organization_id)->toBeNull();
});

it('organization is saved when editing a transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $org = FinancialOrganization::factory()->create(['name' => 'Stripe', 'created_by' => $user->id]);
    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 1000,
        'financial_category_id' => $category->id,
        'organization_id' => null,
        'entered_by' => $user->id,
    ]);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->call('openEditModal', $tx->id)
        ->set('editOrganizationId', $org->id)
        ->set('editOrganizationName', $org->name)
        ->call('updateTransaction');

    expect($tx->fresh()->organization_id)->toBe($org->id);
});

it('organization can be cleared when editing a transaction', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $account = FinancialAccount::factory()->create();
    $category = FinancialCategory::factory()->expense()->create();
    $org = FinancialOrganization::factory()->create(['name' => 'Patreon', 'created_by' => $user->id]);
    $tx = FinancialTransaction::factory()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 2000,
        'financial_category_id' => $category->id,
        'organization_id' => $org->id,
        'entered_by' => $user->id,
    ]);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->call('openEditModal', $tx->id)
        ->call('clearEditOrganization')
        ->call('updateTransaction');

    expect($tx->fresh()->organization_id)->toBeNull();
});

// == Organization Picker == //

it('treasurer can create a new organization inline from the picker', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('organizationSearch', 'New Ministry Partner')
        ->call('createOrganizationInline');

    $this->assertDatabaseHas('financial_organizations', [
        'name' => 'New Ministry Partner',
        'is_archived' => false,
    ]);
});

it('createOrganizationInline sets organizationId and organizationName', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $this->actingAs($user);

    $component = livewire('finances.dashboard')
        ->set('organizationSearch', 'Grace Church')
        ->call('createOrganizationInline');

    $org = FinancialOrganization::where('name', 'Grace Church')->first();
    $component->assertSet('organizationId', $org->id)
        ->assertSet('organizationName', 'Grace Church');
});

it('selectOrganization sets organizationId and organizationName', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $org = FinancialOrganization::factory()->create(['name' => 'Hope Ministry', 'created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->call('selectOrganization', $org->id)
        ->assertSet('organizationId', $org->id)
        ->assertSet('organizationName', 'Hope Ministry');
});

it('clearOrganization resets organizationId and organizationName', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $org = FinancialOrganization::factory()->create(['name' => 'Serve Fund', 'created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('organizationId', $org->id)
        ->set('organizationName', 'Serve Fund')
        ->call('clearOrganization')
        ->assertSet('organizationId', null)
        ->assertSet('organizationName', '');
});

it('duplicate organization names are rejected', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    FinancialOrganization::factory()->create(['name' => 'Existing Org', 'created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('organizationSearch', 'Existing Org')
        ->call('createOrganizationInline')
        ->assertHasErrors(['organizationSearch']);
});

it('financials-view user cannot create an organization inline', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.dashboard')
        ->set('organizationSearch', 'Unauthorized Org')
        ->call('createOrganizationInline')
        ->assertForbidden();

    $this->assertDatabaseMissing('financial_organizations', ['name' => 'Unauthorized Org']);
});

// == Organization Management (Archive) == //

it('financials-manage user can archive an organization', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $org = FinancialOrganization::factory()->create(['is_archived' => false, 'created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('archiveOrganization', $org->id);

    expect($org->fresh()->is_archived)->toBeTrue();
});

it('financials-treasurer cannot archive an organization', function () {
    $user = User::factory()->withRole('Financials - Treasurer')->create();
    $org = FinancialOrganization::factory()->create(['is_archived' => false, 'created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('archiveOrganization', $org->id)
        ->assertForbidden();

    expect($org->fresh()->is_archived)->toBeFalse();
});

it('financials-view user cannot archive an organization', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $org = FinancialOrganization::factory()->create(['is_archived' => false, 'created_by' => $user->id]);
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('archiveOrganization', $org->id)
        ->assertForbidden();

    expect($org->fresh()->is_archived)->toBeFalse();
});
