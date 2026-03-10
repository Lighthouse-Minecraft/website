<?php

declare(strict_types=1);

use App\Models\SiteConfig;
use Livewire\Volt\Volt;

uses()->group('admin', 'site-config');

it('allows admins to view site settings page', function () {
    $admin = loginAsAdmin();

    SiteConfig::create(['key' => 'test_setting', 'value' => 'test_value', 'description' => 'A test setting']);

    Volt::test('admin-manage-site-configs-page')
        ->assertSee('Site Settings')
        ->assertSee('test_setting')
        ->assertSee('A test setting');
});

it('allows officers to view site settings page', function () {
    $officer = officerCommand();
    loginAs($officer);

    SiteConfig::create(['key' => 'officer_test', 'value' => 'officer_value']);

    Volt::test('admin-manage-site-configs-page')
        ->assertSee('officer_test');
});

it('denies crew members from editing site settings', function () {
    $crew = crewCommand();
    loginAs($crew);

    $config = SiteConfig::create(['key' => 'crew_test', 'value' => 'original']);

    Volt::test('admin-manage-site-configs-page')
        ->call('startEdit', $config->id)
        ->assertForbidden();
});

it('allows editing a config value', function () {
    $admin = loginAsAdmin();

    $config = SiteConfig::create(['key' => 'editable', 'value' => 'old_value', 'description' => 'Editable setting']);

    Volt::test('admin-manage-site-configs-page')
        ->call('startEdit', $config->id)
        ->assertSet('editingId', $config->id)
        ->set('editValue', 'new_value')
        ->call('saveEdit');

    expect($config->fresh()->value)->toBe('new_value');
});
