<?php

use App\Models\PrayerCountry;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

describe('Prayer Management Panel - Display', function () {
    it('should display the Prayer management tab', function () {
        loginAsAdmin();

        get(route('acp.index', ['category' => 'config']))
            ->assertStatus(200)
            ->assertSee('Prayer Nations');
    })->done();

    // The Prayer Management component is displayed
    it('should display the Prayer Management component', function () {
        loginAsAdmin();

        get(route('acp.index', ['category' => 'config', 'tab' => 'prayer-manager']))
            ->assertSeeLivewire('prayer.manage-months');
    })->done();

})->done(issue: 105, assignee: 'jonzenor');

describe('Prayer Management Panel - Dates', function () {
    // The component displays a list of months
    it('should display a list of months', function () {
        loginAsAdmin();

        livewire('prayer.manage-months')
            ->assertSee('January')
            ->assertSee('February')
            ->assertSee('March')
            ->assertSee('April')
            ->assertSee('May')
            ->assertSee('June')
            ->assertSee('July')
            ->assertSee('August')
            ->assertSee('September')
            ->assertSee('October')
            ->assertSee('November')
            ->assertSee('December');
    })->done();

    // The months open a modal with list of days
    it('should open a modal with a list of days when a month is clicked', function () {
        loginAsAdmin();

        livewire('prayer.manage-months')
            ->call('openMonthModal', '1')
            ->assertSee('Manage January');
    })->done();
})->done(issue: 105, assignee: 'jonzenor');

describe('Prayer Management Panel - Data', function () {
    // The date picker shows a new form for if the data doesn't exist for today
    it('should show a new form for today if no data exists', function () {
        loginAsAdmin();

        livewire('prayer.manage-months')
            ->call('openMonthModal', '1')
            ->assertSee('Save Prayer Data');
    })->done();

    // The date picker selects the data for today if it exists
    it('should load existing data for today if it exists', function () {
        // Create a prayer country entry for today
        $country = PrayerCountry::factory()->create();
        loginAsAdmin();
        $date = explode('-', $country->day);

        livewire('prayer.manage-months')
            ->set('day', $date[1])
            ->call('openMonthModal', $date[0])
            ->assertSet('prayerName', $country->name)
            ->assertSet('prayerOperationWorldUrl', $country->operation_world_url)
            ->assertSet('prayerPrayerCastUrl', $country->prayer_cast_url);
    })->done();

    // Saving the changes updates the database
    it('should save the changes to the database', function () {
        loginAsAdmin();

        livewire('prayer.manage-months')
            ->call('openMonthModal', '1')
            ->set('prayerName', 'New Prayer Name')
            ->set('prayerOperationWorldUrl', 'https://new-url.com')
            ->set('prayerPrayerCastUrl', 'https://new-url.com')
            ->call('savePrayerData')
            ->assertStatus(200);

        // Assert that the data was saved in the database
        $this->assertDatabaseHas('prayer_countries', [
            'day' => '1-1',
            'name' => 'New Prayer Name',
            'operation_world_url' => 'https://new-url.com',
            'prayer_cast_url' => 'https://new-url.com',
        ]);
    })->done();

})->done(issue: 105, assignee: 'jonzenor');

describe('Prayer Management Panel - Permissions', function () {
    // The Command and Chaplain departments can view the panel
    it('should allow Command and Chaplain departments to view the panel', function ($user) {
        loginAs($user);

        get(route('acp.index', ['category' => 'config']))
            ->assertStatus(200)
            ->assertSee('Prayer Nations');
    })->with([
        'Officer Command' => fn () => officerCommand(),
        'Officer Chaplain' => fn () => officerChaplain(),
    ])->done();

    // The other officer departments cannot view the panel
    it('should prevent other officer departments from viewing the panel', function ($user) {
        loginAs($user);

        get(route('acp.index', ['category' => 'config']))
            ->assertDontSee('Prayer Nations');
    })->with([
        'Officer Engineer' => fn () => officerEngineer(),
        'Officer Quartermaster' => fn () => officerQuartermaster(),
        'Officer Steward' => fn () => officerSteward(),
    ])->done();

})->done(issue: 105, assignee: 'jonzenor');
