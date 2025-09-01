<?php

use function Pest\Laravel\get;

describe('Donation Page', function () {
    // The donation dashboard loads
    it('loads successfully', function () {
        loginAsAdmin();

        get(route('donate'))
            ->assertOk()
            ->assertViewIs('donation.index');
    });

    // The donation dashboard links in the sidebar
    it('is linked in the sidebar', function () {
        loginAsAdmin();

        get(route('dashboard'))
            ->assertOk()
            ->assertSee('Donate')
            ->assertSee(route('donate'));
    });

});
