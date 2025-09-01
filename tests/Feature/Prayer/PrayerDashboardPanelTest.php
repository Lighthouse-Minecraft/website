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
        // Use America/New_York timezone since that's the default for users without timezone
        $today = now()->setTimezone('America/New_York')->format('n-j');
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
        // Use America/New_York timezone since that's the default for users without timezone
        $today = now()->setTimezone('America/New_York')->format('n-j');
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
        // Use America/New_York timezone since that's the default for users without timezone
        $today = now()->setTimezone('America/New_York')->format('n-j');
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
        // Use America/New_York timezone since that's the default for users without timezone
        $today = now()->setTimezone('America/New_York')->format('n-j');
        $year = now()->setTimezone('America/New_York')->format('Y');
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

    // The buttons turns gray if the user has already prayed this year
    it('should disable the button if the user has already prayed for this country this year', function () {
        $user = loginAsAdmin();
        $today = now()->setTimezone('America/New_York')->format('n-j');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();

        // First time - should work and show success message
        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday')
            ->assertOk()
            ->assertSee('Thank you for Praying');

        // Second attempt should show warning and not create duplicate record
        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday')
            ->assertOk();

        // Should only have one record for this year
        $this->assertEquals(1, $user->prayerCountries()->count());
    });

    // The prayer status is cached for performance
    it('should cache the users prayer status', function () {
        $user = loginAsAdmin();
        $today = now()->setTimezone('America/New_York')->format('n-j');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();

        // Create a prayer record directly in the database
        $user->prayerCountries()->attach($prayerNation->id, [
            'year' => now()->format('Y'),
        ]);

        // The widget should detect the user has already prayed (from cache)
        livewire('prayer.prayer-widget')
            ->assertSet('hasPrayedToday', true)
            ->assertSee('Thank you for Praying')
            ->assertDontSee('I Prayed Today');
    });

    // The button records the user's streak on their profile
    it('should update the users prayer streak on their profile', function () {
        $user = loginAsAdmin();
        $today = now()->setTimezone('America/New_York')->format('n-j');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();

        // First time - should work and show success message
        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday')
            ->assertOk()
            ->assertSee('Thank you for Praying');

        $user->refresh();
        $this->assertEquals(1, $user->prayer_streak);
    });

    // The prayer streak is reset if the user missed a day
    it('should reset the users prayer streak if they miss a day', function () {
        $user = loginAsAdmin();
        $today = now()->setTimezone('America/New_York')->format('n-j');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();

        $user->prayer_streak = 5;
        $user->last_prayed_at = now()->subDays(2);
        $user->save();

        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday')
            ->assertOk()
            ->assertSee('Thank you for Praying');

        $user->refresh();
        $this->assertEquals(1, $user->prayer_streak);
    });

    // The dashboard widget shows the users streak
    it('should show the users prayer streak', function () {
        $user = loginAsAdmin();
        $today = now()->setTimezone('America/New_York')->format('n-j');

        $user->prayer_streak = 200005;
        $user->save();

        // First time - should work and show success message
        livewire('prayer.prayer-widget')
            ->assertSee('Prayer Streak')
            ->assertSee('200005');
    });
})->done(issue: 107, assignee: 'jonzenor');

describe('Prayer Dashboard Panel - Community Stats', function () {
    // Clicking on the prayed button adds to the daily stats
    it('adds to the stats when clicking I Prayed', function () {
        $user = loginAsAdmin();
        $today = now()->setTimezone('America/New_York')->format('n-j');
        $prayerNation = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday')
            ->assertOk();

        // Check that the daily stats have been updated
        $this->assertDatabaseHas('prayer_country_stats', [
            'year' => now()->setTimezone('America/New_York')->format('Y'),
            'prayer_country_id' => $prayerNation->id,
            'count' => 1,
        ]);
    });

    // The widget shows how many community members prayed that day

})->wip(issue: 108, assignee: 'jonzenor');
