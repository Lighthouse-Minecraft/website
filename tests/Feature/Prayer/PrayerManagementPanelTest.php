<?php

use function Pest\Laravel\get;

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

describe('Prayer Management Panel - Editing', function () {
    // The component displays a list of months

    // The months open a modal with list of days

    // The days open a modal to edit that days info

    // Saving the changes updates the database
})->todo(issue: 105, assignee: 'jonzenor');

describe('Prayer Management Panel - Permissions', function () {
    // The Command and Chaplain departments can view the panel

    // The other officer departments cannot view the panel

})->todo(issue: 105, assignee: 'jonzenor');
