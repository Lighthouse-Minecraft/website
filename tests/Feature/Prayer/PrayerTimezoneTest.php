<?php

use App\Models\PrayerCountry;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Livewire\livewire;

describe('Prayer Widget - Timezone Handling', function () {
    it('uses America/New_York as default timezone when user timezone is null', function () {
        $user = User::factory()->create(['timezone' => null]);
        $this->actingAs($user);

        // Create a prayer country for today in New York timezone
        $nyTime = Carbon::now('America/New_York');
        $today = $nyTime->format('n-d');
        $prayerCountry = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->assertSet('day', $today)
            ->assertSet('prayerCountry.name', $prayerCountry->name);
    });

    it('uses the users timezone when set', function () {
        $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $this->actingAs($user);

        // Create a prayer country for today in Los Angeles timezone
        $laTime = Carbon::now('America/Los_Angeles');
        $today = $laTime->format('n-d');
        $prayerCountry = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->assertSet('day', $today)
            ->assertSet('prayerCountry.name', $prayerCountry->name);
    });

    it('uses user timezone for prayer year calculations', function () {
        $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $this->actingAs($user);

        $laTime = Carbon::now('America/Los_Angeles');
        $expectedYear = $laTime->year;
        $today = $laTime->format('n-d');
        $prayerCountry = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday');

        // Check that the record was saved with the correct year
        $this->assertDatabaseHas('prayer_country_user', [
            'user_id' => $user->id,
            'prayer_country_id' => $prayerCountry->id,
            'year' => $expectedYear,
        ]);
    });

    it('sets last_prayed_at timestamp when marking as prayed', function () {
        $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $this->actingAs($user);

        // Ensure user hasn't prayed before
        expect($user->last_prayed_at)->toBeNull();

        // Create prayer country for today
        $today = Carbon::now('America/Los_Angeles')->format('n-d');
        $prayerCountry = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday');

        $user->refresh();

        // The last_prayed_at should now be set
        expect($user->last_prayed_at)->not->toBeNull();
    });

    it('calculates prayer streaks based on user timezone for consecutive days', function () {
        $user = User::factory()->create([
            'timezone' => 'America/Los_Angeles',
            'prayer_streak' => 5,
        ]);
        $this->actingAs($user);

        // Set last_prayed_at to yesterday in user's timezone
        $yesterday = Carbon::now('America/Los_Angeles')->subDay();
        $user->last_prayed_at = $yesterday;
        $user->save();

        $today = Carbon::now('America/Los_Angeles')->format('n-d');
        $prayerCountry = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday');

        $user->refresh();

        // Streak should be incremented since they prayed yesterday in their timezone
        expect($user->prayer_streak)->toBe(6);
    });

    it('resets streak when user misses more than one day in their timezone', function () {
        $user = User::factory()->create([
            'timezone' => 'America/Los_Angeles',
            'prayer_streak' => 10,
        ]);
        $this->actingAs($user);

        // Set last_prayed_at to 3 days ago in user's timezone
        $threeDaysAgo = Carbon::now('America/Los_Angeles')->subDays(3);
        $user->last_prayed_at = $threeDaysAgo;
        $user->save();

        $today = Carbon::now('America/Los_Angeles')->format('n-d');
        $prayerCountry = PrayerCountry::factory()->withDay($today)->create();

        livewire('prayer.prayer-widget')
            ->call('markAsPrayedToday');

        $user->refresh();

        // Streak should be reset to 1 since they missed more than one day
        expect($user->prayer_streak)->toBe(1);
    });

    it('handles different timezones correctly', function () {
        // Test that different users can have different "today" values
        $nyUser = User::factory()->create(['timezone' => 'America/New_York']);
        $laUser = User::factory()->create(['timezone' => 'America/Los_Angeles']);

        $nyToday = Carbon::now('America/New_York')->format('n-d');
        $laToday = Carbon::now('America/Los_Angeles')->format('n-d');

        // Create prayer countries for both days (they might be the same)
        $nyPrayerCountry = PrayerCountry::factory()->withDay($nyToday)->create();

        // Only create LA prayer country if it's different from NY
        if ($laToday !== $nyToday) {
            $laPrayerCountry = PrayerCountry::factory()->withDay($laToday)->create();
        }

        // Test NY user
        $this->actingAs($nyUser);
        livewire('prayer.prayer-widget')
            ->assertSet('day', $nyToday);

        // Test LA user
        $this->actingAs($laUser);
        livewire('prayer.prayer-widget')
            ->assertSet('day', $laToday);
    });
})->done(issue: 'timezone-support', assignee: 'jonzenor');
