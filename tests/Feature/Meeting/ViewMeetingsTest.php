<?php

use function Pest\Laravel\get;

uses()->group('feature');

beforeEach(function () {
    // $this->meetings = Meeting::factory()->count(3)->create();
});

describe('Meetings List Page - Load', function () {
    it('loads the Meetings List page', function () {
        get(route('meeting.index'))
            ->assertOk();
    });

    it('displays a list of meetings', function () {

    })->todo();
});

describe('Meetings List Page - Permissions', function() {
    it('shows a 404 if an unauthorized person views the page', function () {

    })->todo();

    it('allows members to view the page', function () {

    })->todo();
})->todo();

describe('Meetings List Page - Functionality', function () {
    it('links to the individual meeting pages', function () {

    })->todo();
})->todo();
