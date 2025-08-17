<?php

use function Pest\Laravel\get;

describe('Community Updates Navigation page', function () {
    // Show a link in the sidebar navigation
    it('shows a link in the sidebar navigation', function () {
        loginAsAdmin();

        get('dashboard')
            ->assertSee('Community Updates')
            ->assertSee(route('community-updates.index'));
    });

    // Traveler and above should see the updates navigation
    it('shows the updates navigation for Traveler and above', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertSee('Community Updates')
            ->assertSee(route('community-updates.index'));
    })->with('memberAtLeastTraveler');

    // Below traveler should not see the updates navigation
    it('does not show the updates navigation for Stowaway and below', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Community Updates')
            ->assertDontSee(route('community-updates.index'));
    })->with('memberAtMostStowaway');

});

describe('Community Updates page', function () {
    // The Community Updates page loads

    // Traveler and above should have access to the page

    // Below traveler should not have access to the page

});

describe('Community Updates List', function () {
    // List meetings that have been finalized

    // Show the Community Updates from that meeting

    // When Livewire 4 releases, make this an infinite scrolling list
});
