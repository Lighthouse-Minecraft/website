<?php

declare(strict_types=1);

use App\Models\SiteConfig;
use Illuminate\Support\Facades\Cache;

uses()->group('site-config', 'models');

it('can get a config value by key', function () {
    SiteConfig::create(['key' => 'test_key', 'value' => 'test_value']);

    Cache::flush();

    expect(SiteConfig::getValue('test_key'))->toBe('test_value');
});

it('returns default when key does not exist', function () {
    expect(SiteConfig::getValue('nonexistent', 'fallback'))->toBe('fallback');
});

it('returns null when key does not exist and no default given', function () {
    expect(SiteConfig::getValue('nonexistent'))->toBeNull();
});

it('can set a config value', function () {
    SiteConfig::setValue('new_key', 'new_value');

    expect(SiteConfig::where('key', 'new_key')->first()->value)->toBe('new_value');
});

it('updates existing config when setting value', function () {
    SiteConfig::create(['key' => 'update_key', 'value' => 'original']);

    SiteConfig::setValue('update_key', 'updated');

    expect(SiteConfig::where('key', 'update_key')->first()->value)->toBe('updated');
});

it('clears cache when value is set', function () {
    SiteConfig::create(['key' => 'cached_key', 'value' => 'cached_value']);

    // Prime the cache
    SiteConfig::getValue('cached_key');
    expect(Cache::has('site_config.cached_key'))->toBeTrue();

    // Setting should clear cache
    SiteConfig::setValue('cached_key', 'new_value');
    expect(Cache::has('site_config.cached_key'))->toBeFalse();
});
