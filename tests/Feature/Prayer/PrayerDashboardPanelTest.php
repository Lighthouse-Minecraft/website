<?php

use App\Models\PrayerCountry;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

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
    it('does not show PrayerCast if the URL is null', function () {
        $today = now()->format('n-d');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create([
            'prayer_cast_url' => null,
        ]);
        loginAsAdmin();

        get(route('dashboard'))
            ->assertOk()
            ->assertSee($prayerNation->name)
            ->assertSee('Operation World')
            ->assertSee($prayerNation->operation_world_url)
            ->assertDontSee('PrayerCast Video');
    })->done();
})->done(issue: 106, assignee: 'jonzenor');

describe('Prayer Dashboard Panel - Permissions', function () {
    // The dashboard widget shows for all Stowaway and above
    it('should allow Stowaway and above to view the panel', function ($user) {
        $today = now()->format('n-d');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();
        loginAs($user);

        get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operation World')
            ->assertSeeLivewire('prayer.prayer-widget');
    })->with('memberAtLeastStowaway')->done();

    // The dashboard widget is not visible for Drifters
    it('should not allow Drifters to view the panel', function ($user) {
        loginAs($user);

        get(route('dashboard'))
            ->assertDontSee('Pray Today');
    })->with('memberAtMostDrifter')->done();
})->done(issue: 106, assignee: 'jonzenor');

describe('Prayer Dashboard Panel - I Prayed Today Button', function () {
    // There is a button to record that a user has prayed today
    it('should display a button to mark the prayer as prayed for today', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertOk()
            ->assertSee('I Prayed Today');
    })->done();

    // Clicking the button saves an entry in the prayer_country_user table
    it('should save an entry in the db when clicked', function () {
        $user = loginAsAdmin();
        $today = now()->format('n-d');
        $year = now()->format('Y');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday')
            ->assertOk();

        $this->assertDatabaseHas('prayer_country_user', [
            'user_id' => $user->id,
            'prayer_country_id' => $prayerNation->id,
            'year' => $year,
        ]);
    });

    // The buttons turns gray if the user has already prayed today

    // The button records the user's streak on their profile

    // The dashboard widget shows the users streak
})->wip(issue: 107, assignee: 'jonzenor');
