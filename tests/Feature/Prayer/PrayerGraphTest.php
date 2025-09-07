<?php

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

describe('Prayer Graph - Dashboard', function () {
    // The graph widget should show on the dashboard
    it('should show the graph widget', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertStatus(200)
            ->assertSeeLivewire('prayer.prayer-graph');
    });

    // The graph should load x days of data
    it('should load 7 days of data', function () {
        // Add entries to the prayer_country_stats table for the last 7 days
        foreach (range(0, 6) as $i) {
            $date = now()->subDays($i);
            $country = \App\Models\PrayerCountry::factory()->create();

            \App\Models\PrayerCountryStat::factory()->create([
                'prayer_country_id' => $country->id,
                'created_at' => $date,
                'count' => rand(1, 100),
            ]);
        }
        loginAsAdmin();

        // Assert that the livewire variable has the data in an array for the last 7 days
        $data = livewire('prayer.prayer-graph')->get('data');
        expect($data)->toBeArray();
        expect($data)->toHaveCount(8);
    });

})->done(issue: 169, assignee: 'jonzenor');
