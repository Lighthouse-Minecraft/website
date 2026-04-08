<?php

declare(strict_types=1);

use App\Models\FinancialTag;
use App\Models\FinancialVendor;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finance', 'vendors', 'tags');

// == Vendor management page == //

it('Finance - View user can access vendors page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.vendors.index'))
        ->assertOk();
});

it('non-finance user is forbidden from vendors page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('finance.vendors.index'))
        ->assertForbidden();
});

it('Finance - Manage user can create a vendor', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.vendors')
        ->set('newName', 'Apex Hosting')
        ->call('createVendor');

    expect(FinancialVendor::where('name', 'Apex Hosting')->exists())->toBeTrue();
    expect(FinancialVendor::where('name', 'Apex Hosting')->first()->is_active)->toBeTrue();
});

it('Finance - View user cannot create a vendor', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    Volt::actingAs($user)
        ->test('finance.vendors')
        ->set('newName', 'Test Vendor')
        ->call('createVendor')
        ->assertForbidden();
});

it('validates vendor name is required', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.vendors')
        ->call('createVendor')
        ->assertHasErrors(['newName']);
});

it('Finance - Manage user can deactivate a vendor', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $vendor = FinancialVendor::factory()->create();

    Volt::actingAs($user)
        ->test('finance.vendors')
        ->call('deactivate', $vendor->id);

    expect($vendor->fresh()->is_active)->toBeFalse();
});

it('Finance - Manage user can reactivate a vendor', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $vendor = FinancialVendor::factory()->inactive()->create();

    Volt::actingAs($user)
        ->test('finance.vendors')
        ->call('reactivate', $vendor->id);

    expect($vendor->fresh()->is_active)->toBeTrue();
});

it('deactivated vendor record is preserved in the database', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $vendor = FinancialVendor::factory()->create(['name' => 'Old Vendor']);

    Volt::actingAs($user)
        ->test('finance.vendors')
        ->call('deactivate', $vendor->id);

    expect(FinancialVendor::where('name', 'Old Vendor')->exists())->toBeTrue();
    expect(FinancialVendor::where('name', 'Old Vendor')->first()->is_active)->toBeFalse();
});

// == Tag management page == //

it('Finance - View user can access tags page', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    $this->actingAs($user)
        ->get(route('finance.tags.index'))
        ->assertOk();
});

it('Finance - Manage user can create a tag', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.tags')
        ->set('newName', 'Donations')
        ->set('newColor', 'green')
        ->call('createTag');

    expect(FinancialTag::where('name', 'Donations')->exists())->toBeTrue();
    expect(FinancialTag::where('name', 'Donations')->first()->color)->toBe('green');
});

it('Finance - View user cannot create a tag', function () {
    $user = User::factory()->withRole('Finance - View')->create();

    Volt::actingAs($user)
        ->test('finance.tags')
        ->set('newName', 'Test')
        ->set('newColor', 'blue')
        ->call('createTag')
        ->assertForbidden();
});

it('Finance - Manage user can delete a tag with no journal entries', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();
    $tag = FinancialTag::factory()->create();

    Volt::actingAs($user)
        ->test('finance.tags')
        ->call('delete', $tag->id);

    expect(FinancialTag::find($tag->id))->toBeNull();
});

it('validates tag name is required', function () {
    $user = User::factory()->withRole('Finance - Manage')->create();

    Volt::actingAs($user)
        ->test('finance.tags')
        ->call('createTag')
        ->assertHasErrors(['newName']);
});

// == Vendor search modal == //

it('vendor search modal shows active vendors matching search', function () {
    FinancialVendor::factory()->create(['name' => 'Apex Hosting', 'is_active' => true]);
    FinancialVendor::factory()->create(['name' => 'Bluehost', 'is_active' => true]);
    FinancialVendor::factory()->inactive()->create(['name' => 'Old Corp']);

    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.vendor-search-modal');
    $component->set('search', 'Apex');

    $results = $component->get('results');
    $names = collect($results)->pluck('name')->toArray();

    expect($names)->toContain('Apex Hosting');
    expect($names)->not->toContain('Bluehost');
    expect($names)->not->toContain('Old Corp');
});

it('vendor search modal does not show inactive vendors', function () {
    FinancialVendor::factory()->inactive()->create(['name' => 'Inactive Corp']);

    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.vendor-search-modal');
    $component->set('search', 'Inactive');

    $results = $component->get('results');
    $names = collect($results)->pluck('name')->toArray();

    expect($names)->not->toContain('Inactive Corp');
});

it('vendor search modal shows create option when no exact match', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.vendor-search-modal');
    $component->set('search', 'NewCompanyXYZ');

    expect($component->get('showCreateOption'))->toBeTrue();
});

it('vendor search modal does not show create option for exact name match', function () {
    FinancialVendor::factory()->create(['name' => 'ExactMatch Co']);

    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.vendor-search-modal');
    $component->set('search', 'ExactMatch Co');

    expect($component->get('showCreateOption'))->toBeFalse();
});

it('vendor search modal creates vendor and dispatches event on createAndSelect', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.vendor-search-modal');
    $component->set('search', 'Brand New Vendor');
    $component->call('createAndSelect');

    expect(FinancialVendor::where('name', 'Brand New Vendor')->exists())->toBeTrue();
    $component->assertDispatched('vendor-selected');
});

// == Tag search modal == //

it('tag search modal shows tags matching search', function () {
    FinancialTag::factory()->create(['name' => 'Donations']);
    FinancialTag::factory()->create(['name' => 'Hosting']);

    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.tag-search-modal');
    $component->set('search', 'Donat');

    $results = $component->get('results');
    $names = collect($results)->pluck('name')->toArray();

    expect($names)->toContain('Donations');
    expect($names)->not->toContain('Hosting');
});

it('tag search modal excludes already-selected tags', function () {
    $tag1 = FinancialTag::factory()->create(['name' => 'Alpha']);
    $tag2 = FinancialTag::factory()->create(['name' => 'Beta']);

    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.tag-search-modal');
    $component->set('selectedTagIds', [$tag1->id]);
    $component->set('search', '');

    $results = $component->get('results');
    $ids = collect($results)->pluck('id')->toArray();

    expect($ids)->not->toContain($tag1->id);
    expect($ids)->toContain($tag2->id);
});

it('tag search modal shows create option for new tag name', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.tag-search-modal');
    $component->set('search', 'UniqueNewTagXYZ');

    expect($component->get('showCreateOption'))->toBeTrue();
});

it('tag search modal creates tag and dispatches event on createAndSelect', function () {
    $user = User::factory()->withRole('Finance - Record')->create();

    $component = Volt::actingAs($user)->test('finance.tag-search-modal');
    $component->set('search', 'NewTagFromModal');
    $component->call('createAndSelect');

    expect(FinancialTag::where('name', 'NewTagFromModal')->exists())->toBeTrue();
    $component->assertDispatched('tag-selected');
});
