<?php

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

describe('Prayer Management Panel - Display', function () {
    it('should display the Prayer management tab', function () {
        loginAsAdmin();

        get(route('acp.index'))
            ->assertStatus(200)
            ->assertSee('Prayer Nations');
    })->done();

    // The Prayer Management component is displayed
    it('should display the Prayer Management component', function () {
        loginAsAdmin();

        get(route('acp.index'))
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
    });

    // The date picker selects the data for today if it exists

    // Saving the changes updates the database

})->wip(issue: 105, assignee: 'jonzenor');

describe('Prayer Management Panel - Permissions', function () {
    // The Command and Chaplain departments can view the panel

    // The other officer departments cannot view the panel

})->todo(issue: 105, assignee: 'jonzenor');
