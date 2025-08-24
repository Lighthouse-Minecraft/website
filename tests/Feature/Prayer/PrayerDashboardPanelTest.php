<?php

use App\Models\PrayerCountry;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\get;

describe('Prayer Dashboard Panel - Display', function () {
    // The dashboard widget displays
    it('displays the prayer widget on the dashboard', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pray Today')
            ->assertSeeLivewire('prayer.prayer-widget');
    })->done();

    // The dashboard widget shows a link to the lighthouse prayer list
    it('shows a link to the lighthouse prayer list', function () {
        Config::set('lighthouse.prayer_list_url', 'https://example.com/prayer-list');
        loginAsAdmin();

        get(route('dashboard'))
            ->assertOk()
            ->assertSee(config('lighthouse.prayer_list_url'));
    })->done();

    // The dashboard widget shows todays prayer country
    it('shows a link to Operation World and PrayerCast', function () {
        $today = now()->format('n-d');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();
        loginAsAdmin();

        get(route('dashboard'))
            ->assertOk()
            ->assertSee($prayerNation->name)
            ->assertSee('Operation World')
            ->assertSee($prayerNation->operation_world_url)
            ->assertSee($prayerNation->prayercast_url)
            ->assertSee('PrayerCast Video');
    })->done();

    // The dashboard widget does not show the prayercast video if it is null
})->wip(issue: 106, assignee: 'jonzenor');

describe('Prayer Dashboard Panel - Permissions', function () {
    // The dashboard widget shows for all Stowaway and above
})->todo(issue: 106, assignee: 'jonzenor');
