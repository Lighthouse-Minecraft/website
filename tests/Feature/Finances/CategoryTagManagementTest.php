<?php

declare(strict_types=1);

use App\Actions\CreateFinancialCategory;
use App\Models\FinancialCategory;
use App\Models\FinancialTag;
use App\Models\User;

use function Pest\Livewire\livewire;

uses()->group('finances', 'categories');

// == Hierarchy Constraints == //

it('allows creating a top-level expense category', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->set('newCatName', 'Test Expense')
        ->set('newCatType', 'expense')
        ->set('newCatParentId', '')
        ->call('createCategory');

    $this->assertDatabaseHas('financial_categories', [
        'name' => 'Test Expense',
        'type' => 'expense',
        'parent_id' => null,
    ]);
});

it('allows creating a subcategory under a top-level category', function () {
    $parent = FinancialCategory::factory()->expense()->create(['name' => 'Infrastructure']);

    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->set('newCatName', 'Web Hosting')
        ->set('newCatType', 'expense')
        ->set('newCatParentId', (string) $parent->id)
        ->call('createCategory');

    $this->assertDatabaseHas('financial_categories', [
        'name' => 'Web Hosting',
        'parent_id' => $parent->id,
    ]);
});

it('rejects creating a subcategory under another subcategory', function () {
    $parent = FinancialCategory::factory()->expense()->create();
    $sub = FinancialCategory::factory()->subcategoryOf($parent)->create();

    $this->expectException(\InvalidArgumentException::class);

    CreateFinancialCategory::run('Nested Sub', 'expense', $sub->id);
});

it('assigns sort_order sequentially within a parent scope', function () {
    $parent = FinancialCategory::factory()->expense()->create(['name' => 'Parent For Ordering']);

    $first = CreateFinancialCategory::run('First Sub', 'expense', $parent->id);
    $second = CreateFinancialCategory::run('Second Sub', 'expense', $parent->id);
    $third = CreateFinancialCategory::run('Third Sub', 'expense', $parent->id);

    expect($second->sort_order)->toBe($first->sort_order + 1)
        ->and($third->sort_order)->toBe($second->sort_order + 1);
});

// == Category CRUD via Livewire == //

it('financials-manage user can rename a category', function () {
    $category = FinancialCategory::factory()->expense()->create(['name' => 'Old Name']);
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('openEditCategoryModal', $category->id)
        ->set('editCatName', 'New Name')
        ->call('updateCategory');

    expect($category->fresh()->name)->toBe('New Name');
});

it('financials-manage user can archive a category', function () {
    $category = FinancialCategory::factory()->expense()->create();
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('archiveCategory', $category->id);

    expect($category->fresh()->is_archived)->toBeTrue();
});

it('financials-manage user can reorder a category', function () {
    $category = FinancialCategory::factory()->expense()->create(['sort_order' => 0]);
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('openReorderModal', $category->id)
        ->set('reorderSortOrder', 5)
        ->call('reorderCategory');

    expect($category->fresh()->sort_order)->toBe(5);
});

// == Tag CRUD via Livewire == //

it('financials-manage user can create a tag', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->set('newTagName', 'test-server')
        ->call('createTag');

    $this->assertDatabaseHas('financial_tags', [
        'name' => 'test-server',
        'created_by' => $user->id,
        'is_archived' => false,
    ]);
});

it('financials-manage user can archive a tag', function () {
    $user = User::factory()->withRole('Financials - Manage')->create();
    $tag = FinancialTag::factory()->create(['is_archived' => false]);
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('archiveTag', $tag->id);

    expect($tag->fresh()->is_archived)->toBeTrue();
});

// == Authorization == //

it('user without financials-manage cannot create a category', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->set('newCatName', 'Unauthorized')
        ->set('newCatType', 'expense')
        ->call('createCategory')
        ->assertForbidden();
});

it('user without financials-manage cannot rename a category', function () {
    $category = FinancialCategory::factory()->expense()->create(['name' => 'Original']);
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('openEditCategoryModal', $category->id)
        ->assertForbidden();

    expect($category->fresh()->name)->toBe('Original');
});

it('user without financials-manage cannot archive a category', function () {
    $category = FinancialCategory::factory()->expense()->create(['is_archived' => false]);
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('archiveCategory', $category->id)
        ->assertForbidden();

    expect($category->fresh()->is_archived)->toBeFalse();
});

it('user without financials-manage cannot create a tag', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $this->actingAs($user);

    livewire('finances.categories')
        ->set('newTagName', 'unauthorized-tag')
        ->call('createTag')
        ->assertForbidden();

    $this->assertDatabaseMissing('financial_tags', ['name' => 'unauthorized-tag']);
});

it('user without financials-manage cannot archive a tag', function () {
    $user = User::factory()->withRole('Financials - View')->create();
    $tag = FinancialTag::factory()->create(['is_archived' => false]);
    $this->actingAs($user);

    livewire('finances.categories')
        ->call('archiveTag', $tag->id)
        ->assertForbidden();

    expect($tag->fresh()->is_archived)->toBeFalse();
});
