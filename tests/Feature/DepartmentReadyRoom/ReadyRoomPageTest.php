<?php

use function Pest\Laravel\get;

describe('Ready Room Page', function () {
    // The Ready Room link shows up in the sidebar for all ranks
    it('shows the Ready Room link in the sidebar for all ranks', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertSee('Staff Ready Room')
            ->assertSee(route('ready-room.index'));
    })->with('rankAtLeastJrCrew')->done();

    // The Ready Room link does not show up for members
    it('does not show the Ready Room link in the sidebar for members', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Staff Ready Room')
            ->assertDontSee(route('ready-room.index'));
    })->with('memberAll')->done();

    // The Ready Room page loads
    it('loads the Ready Room page', function () {
        loginAsAdmin();

        get(route('ready-room.index'))
            ->assertStatus(200)
            ->assertViewIs('dashboard.ready-room');
    })->wip();

    // The Ready Room page is accessible by all ranks
    it('is accessible by all ranks', function ($user) {
        loginAs($user);

        get(route('ready-room.index'))
            ->assertStatus(200);
    })->with('rankAtLeastJrCrew')->done();

    // The Ready Room page is not accessible by members
    it('is not accessible by members', function ($user) {
        loginAs($user);

        get(route('ready-room.index'))
            ->assertStatus(403);
    })->with('memberAll')->done();

    // The Ready Room is not accessible to guests
    it('is not accessible to guests', function () {
        get(route('ready-room.index'))
            ->assertRedirect(route('login'));
    })->done();
})->wip(issue: 54, assignee: 'jonzenor');

// The list of departments is displayed as a tab list

// Only Officers can view all of the departments

// JrCrew and Crew Members can only view their department

// The recent meeting notes are displayed as a list

// The current task list is displayed
